<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Centro;
use App\Models\Derivacion;
use App\Models\Joven;
use App\Models\Solicitud;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Correo sin distinguir mayúsculas, máximo de solicitudes en curso, código oculto al centro
 * que rechazó, límite al probar la contraseña actual, registro de consultas fallidas e índices.
 * Ejecutar: php artisan test --filter=CorreoLimitesYPrivacidadTest
 */
class CorreoLimitesYPrivacidadTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Prueba2026!';

    private const FRONTEND = ['Origin' => 'http://localhost:5173'];

    public function test_el_registro_guarda_el_correo_en_minusculas(): void
    {
        $this->postJson('/api/registro', $this->datosRegistro('Nuevo.Joven@Prueba.PE'))->assertCreated();

        $this->assertDatabaseHas('usuarios', ['correo' => 'nuevo.joven@prueba.pe']);
    }

    public function test_no_se_registra_el_mismo_correo_con_otras_mayusculas(): void
    {
        $this->crearJoven('joven@prueba.pe');

        $this->postJson('/api/registro', $this->datosRegistro('JOVEN@prueba.pe'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('correo');
        $this->assertSame(1, Usuario::count());
    }

    public function test_una_cuenta_antigua_guardada_con_mayusculas_tambien_cuenta_como_registrada(): void
    {
        // Simula una cuenta creada antes de esta corrección (el correo quedó con mayúsculas)
        DB::table('usuarios')->insert([
            'correo' => 'Antigua@Prueba.pe', 'contrasena_hash' => Hash::make(self::CLAVE), 'rol' => 'ORIENTADOR', 'activo' => true,
        ]);

        $this->postJson('/api/registro', $this->datosRegistro('antigua@prueba.pe'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('correo');
    }

    public function test_se_inicia_sesion_aunque_el_correo_se_escriba_con_mayusculas(): void
    {
        $this->crearJoven('joven@prueba.pe');

        $this->withHeaders(self::FRONTEND)
            ->postJson('/api/login', ['correo' => 'Joven@Prueba.pe', 'password' => self::CLAVE])
            ->assertOk();
    }

    public function test_una_cuenta_antigua_con_mayusculas_sigue_iniciando_sesion(): void
    {
        DB::table('usuarios')->insert([
            'correo' => 'Antigua@Prueba.pe', 'contrasena_hash' => Hash::make(self::CLAVE), 'rol' => 'ORIENTADOR', 'activo' => true,
        ]);

        $this->withHeaders(self::FRONTEND)
            ->postJson('/api/login', ['correo' => 'Antigua@Prueba.pe', 'password' => self::CLAVE])->assertOk();
        $this->postJson('/api/logout')->assertNoContent();
        $this->withHeaders(self::FRONTEND)
            ->postJson('/api/login', ['correo' => 'antigua@prueba.pe', 'password' => self::CLAVE])->assertOk();
    }

    public function test_el_admin_no_crea_ni_cambia_a_un_correo_que_ya_existe_con_otras_mayusculas(): void
    {
        $admin = $this->crearUsuario('ADMIN', 'admin@prueba.pe');
        $orientador = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');

        $this->actingAs($admin)->postJson('/api/usuarios', [
            'correo' => 'Orientador@Prueba.pe', 'password' => 'ClaveSegura2026', 'rol' => 'ORIENTADOR',
        ])->assertStatus(422)->assertJsonValidationErrors('correo');

        $this->actingAs($admin)->postJson('/api/usuarios', [
            'correo' => 'Nuevo.Orientador@Prueba.pe', 'password' => 'ClaveSegura2026', 'rol' => 'ORIENTADOR',
        ])->assertCreated()->assertJsonPath('data.correo', 'nuevo.orientador@prueba.pe');

        $this->actingAs($admin)->patchJson("/api/usuarios/{$orientador->id}", ['correo' => 'ADMIN@prueba.pe'])
            ->assertStatus(422)->assertJsonValidationErrors('correo');

        // Su propio correo con otras mayúsculas sí se acepta (se guarda en minúsculas)
        $this->actingAs($admin)->patchJson("/api/usuarios/{$orientador->id}", ['correo' => 'Orientador@Prueba.pe'])
            ->assertOk()->assertJsonPath('data.correo', 'orientador@prueba.pe');
    }

    public function test_un_joven_no_puede_tener_mas_de_tres_solicitudes_en_curso(): void
    {
        $joven = $this->crearJoven('joven@prueba.pe');

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($joven)->postJson('/api/solicitudes', $this->datosSolicitud())->assertCreated();
        }
        $this->actingAs($joven)->postJson('/api/solicitudes', $this->datosSolicitud())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ya tienes 3 solicitudes en curso. Espera a que el equipo las revise. Si estás en peligro ahora, llama a la Línea 113, opción 5.');
        $this->assertSame(3, Solicitud::count());
    }

    public function test_las_solicitudes_cerradas_o_canceladas_no_cuentan_para_el_maximo(): void
    {
        $joven = $this->crearJoven('joven@prueba.pe');
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($joven)->postJson('/api/solicitudes', $this->datosSolicitud())->assertCreated();
        }

        $primera = Solicitud::orderBy('id')->first();
        $this->actingAs($joven)->deleteJson("/api/solicitudes/{$primera->id}")->assertNoContent();
        Solicitud::where('id', '!=', $primera->id)->orderBy('id')->first()->update(['estado' => 'CERRADA']);

        $this->actingAs($joven)->postJson('/api/solicitudes', $this->datosSolicitud())->assertCreated();
        $this->actingAs($joven)->postJson('/api/solicitudes', $this->datosSolicitud())->assertCreated();
        $this->actingAs($joven)->postJson('/api/solicitudes', $this->datosSolicitud())->assertStatus(422);
    }

    public function test_el_centro_que_rechazo_ya_no_recibe_el_codigo_de_seguimiento(): void
    {
        [$orientador, $cuentaCentro, $solicitud, $derivacion] = $this->escenarioDerivacion();

        $this->actingAs($cuentaCentro)->getJson("/api/derivaciones/{$derivacion->id}")
            ->assertJsonPath('data.solicitud.codigo', $solicitud->codigo);

        $this->actingAs($cuentaCentro)->patchJson("/api/derivaciones/{$derivacion->id}", ['estado' => 'RECHAZADA'])->assertOk();

        $this->actingAs($cuentaCentro)->getJson("/api/derivaciones/{$derivacion->id}")
            ->assertOk()
            ->assertJsonPath('data.solicitud.codigo', null)
            ->assertJsonPath('data.estado', 'RECHAZADA');
        $this->actingAs($cuentaCentro)->getJson('/api/derivaciones')
            ->assertJsonPath('data.0.solicitud.codigo', null);

        // El orientador sí lo sigue viendo
        $this->actingAs($orientador)->getJson("/api/derivaciones/{$derivacion->id}")
            ->assertJsonPath('data.solicitud.codigo', $solicitud->codigo);
    }

    public function test_probar_la_contrasena_actual_tiene_limite_y_queda_registrado(): void
    {
        $orientador = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($orientador)->patchJson("/api/usuarios/{$orientador->id}", [
                'password_actual' => "Incorrecta{$i}2026", 'password' => 'NuevaClave2026',
            ])->assertStatus(422);
        }
        $this->actingAs($orientador)->patchJson("/api/usuarios/{$orientador->id}", [
            'password_actual' => self::CLAVE, 'password' => 'NuevaClave2026',
        ])->assertStatus(429);

        $this->assertSame(5, Auditoria::where('accion', 'CAMBIO_CONTRASENA_FALLIDO')->where('entidad_id', $orientador->id)->count());
        $this->assertTrue(Hash::check(self::CLAVE, $orientador->fresh()->contrasena_hash));
    }

    public function test_una_consulta_con_un_codigo_inexistente_queda_en_el_log(): void
    {
        Log::spy();

        $this->getJson('/api/consulta/JOV-ZZZZZ')->assertNotFound();

        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($mensaje) => str_contains($mensaje, 'código inexistente'));
    }

    public function test_existen_los_indices_de_busqueda(): void
    {
        $indices = collect(DB::select("SELECT indexname FROM pg_indexes WHERE schemaname = 'public'"))->pluck('indexname');

        $this->assertTrue($indices->contains('solicitudes_joven_id_index'));
        $this->assertTrue($indices->contains('derivaciones_solicitud_id_index'));
        $this->assertTrue($indices->contains('seguimientos_derivacion_id_index'));
    }

    private function escenarioDerivacion(): array
    {
        $orientador = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');
        $centro = Centro::create([
            'nombre' => 'Centro A', 'tipo' => 'MINSA', 'distrito' => 'Arequipa',
            'direccion' => 'Dirección de prueba 100', 'activo' => true,
        ]);
        $cuentaCentro = $this->crearUsuario('CENTRO', 'centro@prueba.pe', $centro->id);
        $joven = $this->crearJoven('joven@prueba.pe');
        $solicitud = Solicitud::create([
            'codigo' => 'JOV-ABCDE', 'joven_id' => $joven->joven->id, 'motivo' => 'Ansiedad',
            'descripcion' => 'Descripción de prueba', 'nivel_riesgo' => 'ALTO', 'canal' => 'WEB', 'estado' => 'DERIVADA',
        ]);
        $derivacion = Derivacion::create([
            'solicitud_id' => $solicitud->id, 'centro_id' => $centro->id,
            'orientador_id' => $orientador->id, 'prioridad' => 3, 'estado' => 'PENDIENTE',
        ]);

        return [$orientador, $cuentaCentro, $solicitud, $derivacion];
    }

    private function datosRegistro(string $correo): array
    {
        return [
            'correo' => $correo,
            'password' => 'ClaveSegura2026',
            'fecha_nacimiento' => now()->subYears(20)->toDateString(),
            'distrito' => 'Cayma',
            'acepta_consentimiento' => true,
        ];
    }

    private function datosSolicitud(): array
    {
        return [
            'motivo' => 'Ansiedad', 'descripcion' => 'Descripción de prueba',
            'nivel_riesgo' => 'BAJO', 'canal' => 'WEB', 'acepta_consentimiento' => true,
        ];
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
        Joven::create(['usuario_id' => $usuario->id, 'fecha_nacimiento' => '2002-05-10', 'distrito' => 'Arequipa']);

        return $usuario;
    }
}
