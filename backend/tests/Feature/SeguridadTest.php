<?php

namespace Tests\Feature;

use App\Models\Centro;
use App\Models\Solicitud;
use App\Models\Usuario;
use App\Models\Joven;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pruebas automatizadas de seguridad (Tabla 19 del informe).
 * Ejecutar: php artisan test --filter=SeguridadTest
 * Requieren una base PostgreSQL de pruebas llamada mente_arequipa_test.
 */
class SeguridadTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Prueba2026!';

    // ---------------------------------------------------------------- Las 7 pruebas de la Tabla 19

    public function test_la_contrasena_se_guarda_con_hash(): void
    {
        $this->postJson('/api/registro', [
            'correo' => 'nuevo@prueba.pe',
            'password' => 'ClaveSegura2026',
            'fecha_nacimiento' => '2002-05-10',
            'distrito' => 'Arequipa',
            'acepta_consentimiento' => true,
        ])->assertCreated();

        $usuario = Usuario::where('correo', 'nuevo@prueba.pe')->firstOrFail();

        $this->assertNotSame('ClaveSegura2026', $usuario->contrasena_hash);
        $this->assertTrue(Hash::check('ClaveSegura2026', $usuario->contrasena_hash));
    }

    public function test_inyeccion_sql_en_el_login_se_rechaza_con_422(): void
    {
        $this->postJson('/api/login', [
            'correo' => "' OR 1=1 --",
            'password' => 'cualquiera',
        ])->assertStatus(422);
    }

    public function test_inyeccion_sql_en_el_codigo_publico_devuelve_404(): void
    {
        $this->getJson('/api/consulta/' . rawurlencode("JOV-' OR 1=1 --"))
            ->assertNotFound();
    }

    public function test_xss_en_la_descripcion_no_se_almacena(): void
    {
        $joven = $this->crearJoven('joven@prueba.pe');

        $respuesta = $this->actingAs($joven)->postJson('/api/solicitudes', [
            'motivo' => 'Ansiedad',
            'descripcion' => '<script>alert(1)</script>Necesito orientación',
            'nivel_riesgo' => 'MEDIO',
            'canal' => 'WEB',
            'acepta_consentimiento' => true,
        ])->assertCreated();

        $guardada = Solicitud::findOrFail($respuesta->json('data.id'));

        $this->assertStringNotContainsString('<script', $guardada->descripcion);
        $this->assertStringContainsString('Necesito orientación', $guardada->descripcion);
    }

    public function test_un_joven_no_ve_solicitudes_ajenas(): void
    {
        $duenio = $this->crearJoven('duenio@prueba.pe');
        $otro = $this->crearJoven('otro@prueba.pe');
        $solicitud = $this->crearSolicitud($duenio);

        $this->actingAs($otro)->getJson("/api/solicitudes/{$solicitud->id}")
            ->assertForbidden();
    }

    public function test_un_joven_no_puede_derivar(): void
    {
        $joven = $this->crearJoven('joven@prueba.pe');
        $solicitud = $this->crearSolicitud($joven, 'EN_REVISION');
        $centro = $this->crearCentro();

        $this->actingAs($joven)->postJson("/api/solicitudes/{$solicitud->id}/derivaciones", [
            'centro_id' => $centro->id,
            'prioridad' => 1,
        ])->assertForbidden();
    }

    public function test_el_sexto_intento_de_login_recibe_429(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/login', [
                'correo' => 'nadie@prueba.pe',
                'password' => 'incorrecta',
            ])->assertStatus(422);
        }

        $this->postJson('/api/login', [
            'correo' => 'nadie@prueba.pe',
            'password' => 'incorrecta',
        ])->assertStatus(429);
    }

    // ---------------------------------------------------------------- Pruebas de las correcciones

    public function test_una_cuenta_desactivada_pierde_el_acceso(): void
    {
        $usuario = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');
        $usuario->update(['activo' => false]);

        $this->actingAs($usuario)->getJson('/api/yo')->assertForbidden();
    }

    public function test_crear_una_solicitud_guarda_el_consentimiento(): void
    {
        $joven = $this->crearJoven('joven@prueba.pe');

        $this->actingAs($joven)->postJson('/api/solicitudes', [
            'motivo' => 'Estrés',
            'descripcion' => 'Necesito orientación',
            'nivel_riesgo' => 'BAJO',
            'canal' => 'WEB',
            'acepta_consentimiento' => true,
        ])->assertCreated();

        $this->assertSame(1, Joven::where('usuario_id', $joven->id)->firstOrFail()->consentimientos()->count());
    }

    public function test_el_centro_ve_sus_derivaciones_y_puede_aceptarlas(): void
    {
        $centro = $this->crearCentro();
        $orientador = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');
        $usuarioCentro = $this->crearUsuario('CENTRO', 'centro@prueba.pe', $centro->id);
        $joven = $this->crearJoven('joven@prueba.pe');
        $solicitud = $this->crearSolicitud($joven, 'EN_REVISION');

        $this->actingAs($orientador)->postJson("/api/solicitudes/{$solicitud->id}/derivaciones", [
            'centro_id' => $centro->id,
            'prioridad' => 1,
        ])->assertCreated();

        $lista = $this->actingAs($usuarioCentro)->getJson('/api/derivaciones')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estado', 'PENDIENTE')
            ->assertJsonPath('data.0.solicitud.codigo', $solicitud->codigo);

        $id = $lista->json('data.0.id');

        $this->actingAs($usuarioCentro)->putJson("/api/derivaciones/{$id}", ['estado' => 'ACEPTADA'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'ACEPTADA');
    }

    // ---------------------------------------------------------------- Ayudas

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

    private function crearCentro(): Centro
    {
        return Centro::create([
            'nombre' => 'Centro de prueba',
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
