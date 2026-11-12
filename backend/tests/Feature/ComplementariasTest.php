<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Centro;
use App\Models\Joven;
use App\Models\Recurso;
use App\Models\Solicitud;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Pruebas complementarias: auditoría inmutable, rango de edad (18 a 29), acceso por rol
 * (centro y admin) y validaciones de usuarios y recursos.
 * Ejecutar: php artisan test --filter=ComplementariasTest
 * Requieren la misma base PostgreSQL de pruebas que SeguridadTest.
 */
class ComplementariasTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Prueba2026!';

    // ---------------------------------------------------------------- Auditoría inmutable

    public function test_la_auditoria_no_permite_modificar_registros(): void
    {
        $this->crearRegistroDeAuditoria();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('solo lectura');

        DB::table('auditoria')->update(['accion' => 'MODIFICADA']);
    }

    public function test_la_auditoria_no_permite_borrar_registros(): void
    {
        $this->crearRegistroDeAuditoria();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('solo lectura');

        DB::table('auditoria')->delete();
    }

    // ---------------------------------------------------------------- Rango de edad (18 a 29 años)

    public function test_el_registro_rechaza_menores_de_18_y_acepta_exactamente_18(): void
    {
        // Cumple 18 mañana: todavía tiene 17 años.
        $this->registroConNacimiento(now()->subYears(18)->addDay()->toDateString())
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha_nacimiento');

        // Cumple 18 hoy.
        $this->registroConNacimiento(now()->subYears(18)->toDateString())
            ->assertCreated();
    }

    public function test_el_registro_rechaza_a_los_30_y_acepta_a_los_29(): void
    {
        // Cumple 30 hoy.
        $this->registroConNacimiento(now()->subYears(30)->toDateString())
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha_nacimiento');

        // Cumple 30 mañana: todavía tiene 29 años.
        $this->registroConNacimiento(now()->subYears(30)->addDay()->toDateString())
            ->assertCreated();
    }

    // ---------------------------------------------------------------- Acceso por rol

    public function test_el_centro_no_accede_a_las_zonas_del_admin(): void
    {
        $centro = $this->crearCentro('Centro A');
        $usuarioCentro = $this->crearUsuario('CENTRO', 'centro@prueba.pe', $centro->id);

        $this->actingAs($usuarioCentro);

        $this->getJson('/api/usuarios')->assertForbidden();
        $this->getJson('/api/auditoria')->assertForbidden();
        $this->getJson('/api/dashboard/resumen')->assertForbidden();
        $this->postJson('/api/recursos', [
            'titulo' => 'Guía',
            'tipo' => 'GUIA',
            'url' => 'https://ejemplo.pe/guia',
            'publicado' => true,
        ])->assertForbidden();
    }

    public function test_un_centro_no_ve_las_solicitudes_de_otro_centro(): void
    {
        $centroA = $this->crearCentro('Centro A');
        $centroB = $this->crearCentro('Centro B');
        $orientador = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');
        $usuarioA = $this->crearUsuario('CENTRO', 'centro.a@prueba.pe', $centroA->id);
        $joven = $this->crearJoven('joven@prueba.pe');
        $solicitud = $this->crearSolicitud($joven, 'EN_REVISION');

        $this->actingAs($orientador)->postJson("/api/solicitudes/{$solicitud->id}/derivaciones", [
            'centro_id' => $centroB->id,
            'prioridad' => 1,
        ])->assertCreated();

        $this->actingAs($usuarioA)->getJson('/api/solicitudes')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/solicitudes/{$solicitud->id}")->assertForbidden();
    }

    // ---------------------------------------------------------------- Usuarios y recursos

    public function test_el_admin_no_puede_desactivar_su_propia_cuenta(): void
    {
        $admin = $this->crearUsuario('ADMIN', 'admin@prueba.pe');

        $this->actingAs($admin)->deleteJson("/api/usuarios/{$admin->id}")->assertStatus(422);

        $this->assertTrue($admin->fresh()->activo);
    }

    public function test_el_admin_valida_los_datos_al_crear_usuarios(): void
    {
        $admin = $this->crearUsuario('ADMIN', 'admin@prueba.pe');

        $this->actingAs($admin);

        // Una cuenta de CENTRO necesita su centro.
        $this->postJson('/api/usuarios', [
            'correo' => 'centro.nuevo@prueba.pe',
            'password' => 'ClaveSegura2026',
            'rol' => 'CENTRO',
        ])->assertStatus(422)->assertJsonValidationErrors('centro_id');

        // La contraseña necesita al menos 10 caracteres.
        $this->postJson('/api/usuarios', [
            'correo' => 'orientador.nuevo@prueba.pe',
            'password' => 'corta',
            'rol' => 'ORIENTADOR',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_los_recursos_no_publicados_solo_los_ve_el_admin(): void
    {
        Recurso::create(['titulo' => 'Visible', 'tipo' => 'GUIA', 'url' => 'https://ejemplo.pe/a', 'publicado' => true]);
        Recurso::create(['titulo' => 'Oculto', 'tipo' => 'GUIA', 'url' => 'https://ejemplo.pe/b', 'publicado' => false]);

        $joven = $this->crearJoven('joven@prueba.pe');
        $admin = $this->crearUsuario('ADMIN', 'admin@prueba.pe');

        $this->actingAs($joven)->getJson('/api/recursos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titulo', 'Visible');

        $this->actingAs($admin)->getJson('/api/recursos')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_un_recurso_rechaza_enlaces_javascript(): void
    {
        $admin = $this->crearUsuario('ADMIN', 'admin@prueba.pe');

        $this->actingAs($admin)->postJson('/api/recursos', [
            'titulo' => 'Enlace peligroso',
            'tipo' => 'ENLACE',
            'url' => 'javascript:alert(1)',
            'publicado' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('url');
    }

    // ---------------------------------------------------------------- Ayudas

    private function registroConNacimiento(string $fecha): TestResponse
    {
        return $this->postJson('/api/registro', [
            'correo' => 'edad.' . md5($fecha) . '@prueba.pe',
            'password' => 'ClaveSegura2026',
            'fecha_nacimiento' => $fecha,
            'distrito' => 'Arequipa',
            'acepta_consentimiento' => true,
        ]);
    }

    private function crearRegistroDeAuditoria(): void
    {
        Auditoria::create([
            'usuario_id' => null,
            'accion' => 'CONSULTA_CASO',
            'entidad' => 'solicitud',
            'entidad_id' => 1,
            'ip' => '127.0.0.1',
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

    private function crearJoven(string $correo): Usuario
    {
        $usuario = $this->crearUsuario('JOVEN', $correo);

        Joven::create([
            'usuario_id' => $usuario->id,
            'fecha_nacimiento' => '2002-05-10',
            'distrito' => 'Arequipa',
        ]);

        return $usuario;
    }

    private function crearCentro(string $nombre): Centro
    {
        return Centro::create([
            'nombre' => $nombre,
            'tipo' => 'MINSA',
            'distrito' => 'Arequipa',
            'direccion' => 'Dirección de prueba 100',
            'activo' => true,
        ]);
    }

    private function crearSolicitud(Usuario $usuarioJoven, string $estado = 'REGISTRADA'): Solicitud
    {
        $joven = Joven::where('usuario_id', $usuarioJoven->id)->firstOrFail();

        return Solicitud::create([
            'codigo' => 'JOV-' . strtoupper(substr(md5(uniqid('', true)), 0, 5)),
            'joven_id' => $joven->id,
            'motivo' => 'Ansiedad',
            'descripcion' => 'Descripción de prueba',
            'nivel_riesgo' => 'MEDIO',
            'canal' => 'WEB',
            'estado' => $estado,
        ])->refresh();
    }
}
