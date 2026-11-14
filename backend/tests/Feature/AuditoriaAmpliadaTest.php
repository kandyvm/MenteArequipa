<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Centro;
use App\Models\Recurso;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * La auditoría registra inicios y cierres de sesión, registros de jóvenes y los cambios
 * de usuarios, centros y recursos.
 * Ejecutar: php artisan test --filter=AuditoriaAmpliadaTest
 */
class AuditoriaAmpliadaTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Prueba2026!';

    /** El login y el logout usan la sesión de Sanctum: la petición debe venir del frontend. */
    private const FRONTEND = ['Origin' => 'http://localhost:5173'];

    public function test_registra_el_inicio_de_sesion_y_el_intento_fallido(): void
    {
        $orientador = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');

        $this->withHeaders(self::FRONTEND);
        $this->postJson('/api/login', ['correo' => 'orientador@prueba.pe', 'password' => 'Incorrecta1'])
            ->assertStatus(422);
        $this->postJson('/api/login', ['correo' => 'orientador@prueba.pe', 'password' => self::CLAVE])
            ->assertOk();

        $this->assertAuditado('INICIO_SESION_FALLIDO', 'usuario', $orientador->id, null);
        $this->assertAuditado('INICIAR_SESION', 'usuario', $orientador->id, $orientador->id);
    }

    public function test_un_correo_que_no_existe_no_deja_registro(): void
    {
        $this->postJson('/api/login', ['correo' => 'nadie@prueba.pe', 'password' => 'Incorrecta1'])
            ->assertStatus(422);

        $this->assertSame(0, Auditoria::count());
    }

    public function test_registra_el_cierre_de_sesion(): void
    {
        $orientador = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');

        $this->withHeaders(self::FRONTEND)->actingAs($orientador)->postJson('/api/logout')->assertNoContent();

        $this->assertAuditado('CERRAR_SESION', 'usuario', $orientador->id, $orientador->id);
    }

    public function test_registra_el_registro_de_un_joven(): void
    {
        $this->postJson('/api/registro', [
            'correo' => 'joven@prueba.pe',
            'password' => 'ClaveSegura2026',
            'fecha_nacimiento' => now()->subYears(20)->toDateString(),
            'distrito' => 'Arequipa',
            'acepta_consentimiento' => true,
        ])->assertCreated();

        $joven = Usuario::where('correo', 'joven@prueba.pe')->firstOrFail();
        $this->assertAuditado('REGISTRAR_JOVEN', 'usuario', $joven->id, $joven->id);
    }

    public function test_registra_los_cambios_de_usuarios(): void
    {
        $admin = $this->crearUsuario('ADMIN', 'admin@prueba.pe');
        $cuenta = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');

        $this->actingAs($admin);
        $this->patchJson("/api/usuarios/{$cuenta->id}", ['correo' => 'otro@prueba.pe'])->assertOk();
        $this->patchJson("/api/usuarios/{$cuenta->id}", ['password' => 'NuevaClave2026!'])->assertOk();
        $this->deleteJson("/api/usuarios/{$cuenta->id}")->assertNoContent();
        $this->patchJson("/api/usuarios/{$cuenta->id}", ['activo' => true])->assertOk();

        foreach (['EDITAR_USUARIO', 'CAMBIAR_CONTRASENA', 'DESACTIVAR_USUARIO', 'REACTIVAR_USUARIO'] as $accion) {
            $this->assertAuditado($accion, 'usuario', $cuenta->id, $admin->id);
        }
    }

    public function test_un_guardado_sin_cambios_no_deja_registro(): void
    {
        $admin = $this->crearUsuario('ADMIN', 'admin@prueba.pe');
        $cuenta = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');

        $this->actingAs($admin)->patchJson("/api/usuarios/{$cuenta->id}", ['correo' => 'orientador@prueba.pe'])
            ->assertOk();

        $this->assertSame(0, Auditoria::count());
    }

    public function test_registra_los_cambios_de_centros(): void
    {
        $admin = $this->crearUsuario('ADMIN', 'admin@prueba.pe');
        $this->actingAs($admin);

        $id = $this->postJson('/api/centros', [
            'nombre' => 'Centro Nuevo',
            'tipo' => 'MINSA',
            'distrito' => 'Arequipa',
            'direccion' => 'Calle 123',
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/centros/{$id}", ['direccion' => 'Calle 456'])->assertOk();
        $this->deleteJson("/api/centros/{$id}")->assertNoContent();
        $this->patchJson("/api/centros/{$id}", ['activo' => true])->assertOk();

        foreach (['CREAR_CENTRO', 'EDITAR_CENTRO', 'DESACTIVAR_CENTRO', 'REACTIVAR_CENTRO'] as $accion) {
            $this->assertAuditado($accion, 'centro', $id, $admin->id);
        }
    }

    public function test_registra_los_cambios_de_recursos(): void
    {
        $admin = $this->crearUsuario('ADMIN', 'admin@prueba.pe');
        $this->actingAs($admin);

        $id = $this->postJson('/api/recursos', [
            'titulo' => 'Guía',
            'tipo' => 'GUIA',
            'url' => 'https://ejemplo.pe/guia',
            'publicado' => false,
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/recursos/{$id}", ['titulo' => 'Guía nueva'])->assertOk();
        $this->patchJson("/api/recursos/{$id}", ['publicado' => true])->assertOk();
        $this->patchJson("/api/recursos/{$id}", ['publicado' => false])->assertOk();
        $this->deleteJson("/api/recursos/{$id}")->assertNoContent();

        foreach (['CREAR_RECURSO', 'EDITAR_RECURSO', 'PUBLICAR_RECURSO', 'OCULTAR_RECURSO', 'ELIMINAR_RECURSO'] as $accion) {
            $this->assertAuditado($accion, 'recurso', $id, $admin->id);
        }
        $this->assertNull(Recurso::find($id));
    }

    private function assertAuditado(string $accion, string $entidad, int $entidadId, ?int $usuarioId): void
    {
        $this->assertDatabaseHas('auditoria', [
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'usuario_id' => $usuarioId,
        ]);
    }

    private function crearUsuario(string $rol, string $correo, ?int $centroId = null): Usuario
    {
        return Usuario::create([
            'correo' => $correo,
            'contrasena_hash' => Hash::make(self::CLAVE),
            'rol' => $rol,
            'centro_id' => $centroId,
            'activo' => true,
        ]);
    }
}
