<?php

namespace Tests\Feature;

use App\Models\Centro;
use App\Models\Derivacion;
use App\Models\Joven;
use App\Models\Solicitud;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Los casos en crisis aparecen primero para el orientador, y un centro desactivado
 * ya no ve ni atiende casos (ni se envía al joven a ese centro).
 * Ejecutar: php artisan test --filter=PrioridadYCentroDesactivadoTest
 */
class PrioridadYCentroDesactivadoTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Prueba2026!';

    private Usuario $orientador;

    private Usuario $joven;

    private Centro $centro;

    private Usuario $cuentaCentro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orientador = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');
        $this->joven = $this->crearUsuario('JOVEN', 'joven@prueba.pe');
        Joven::create(['usuario_id' => $this->joven->id, 'fecha_nacimiento' => '2002-05-10', 'distrito' => 'Arequipa']);
        $this->centro = Centro::create([
            'nombre' => 'Centro A', 'tipo' => 'MINSA', 'distrito' => 'Arequipa',
            'direccion' => 'Dirección de prueba 100', 'activo' => true,
        ]);
        $this->cuentaCentro = $this->crearUsuario('CENTRO', 'centro@prueba.pe', $this->centro->id);
    }

    public function test_un_caso_en_crisis_aparece_primero_aunque_haya_muchos_casos_mas_nuevos(): void
    {
        $crisis = $this->crearSolicitud('CRISIS');
        for ($i = 0; $i < 16; $i++) {
            $this->crearSolicitud('BAJO');
        }

        $this->actingAs($this->orientador)->getJson('/api/solicitudes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $crisis->id)
            ->assertJsonPath('data.0.nivel_riesgo', 'CRISIS');
    }

    public function test_los_casos_cerrados_van_despues_de_los_que_esperan_atencion(): void
    {
        $pendiente = $this->crearSolicitud('BAJO');
        $cerrada = $this->crearSolicitud('CRISIS', 'CERRADA');

        $ids = collect($this->actingAs($this->orientador)->getJson('/api/solicitudes')->json('data'))->pluck('id');

        $this->assertSame([$pendiente->id, $cerrada->id], $ids->all());
    }

    public function test_el_joven_sigue_viendo_sus_solicitudes_por_fecha(): void
    {
        $crisis = $this->crearSolicitud('CRISIS');
        $nueva = $this->crearSolicitud('BAJO');

        $ids = collect($this->actingAs($this->joven)->getJson('/api/solicitudes')->json('data'))->pluck('id');

        $this->assertSame([$nueva->id, $crisis->id], $ids->all());
    }

    public function test_un_centro_desactivado_no_ve_ni_acepta_casos(): void
    {
        $derivacion = $this->derivar($this->crearSolicitud('ALTO', 'DERIVADA'));
        $this->centro->update(['activo' => false]);

        $this->actingAs($this->cuentaCentro)->getJson('/api/solicitudes')
            ->assertForbidden()
            ->assertJsonPath('message', 'El centro de esta cuenta está desactivado.');

        $this->actingAs($this->cuentaCentro)->patchJson("/api/derivaciones/{$derivacion->id}", ['estado' => 'ACEPTADA'])
            ->assertForbidden();
        $this->assertSame('PENDIENTE', $derivacion->fresh()->estado);
    }

    public function test_una_cuenta_de_un_centro_desactivado_no_inicia_sesion(): void
    {
        $this->centro->update(['activo' => false]);

        $this->withHeaders(['Origin' => 'http://localhost:5173'])
            ->postJson('/api/login', ['correo' => 'centro@prueba.pe', 'password' => self::CLAVE])
            ->assertForbidden()
            ->assertJsonPath('message', 'El centro de esta cuenta está desactivado.');
    }

    public function test_un_centro_activo_sigue_funcionando_igual(): void
    {
        $derivacion = $this->derivar($this->crearSolicitud('ALTO', 'DERIVADA'));

        $this->actingAs($this->cuentaCentro)->patchJson("/api/derivaciones/{$derivacion->id}", ['estado' => 'ACEPTADA'])
            ->assertOk();
    }

    public function test_el_orientador_ve_que_el_centro_de_una_derivacion_esta_desactivado(): void
    {
        $solicitud = $this->crearSolicitud('ALTO', 'DERIVADA');
        $this->derivar($solicitud);

        $this->actingAs($this->orientador)->getJson("/api/solicitudes/{$solicitud->id}")
            ->assertJsonPath('data.derivaciones.0.centro_activo', true);

        $this->centro->update(['activo' => false]);

        $this->actingAs($this->orientador)->getJson("/api/solicitudes/{$solicitud->id}")
            ->assertJsonPath('data.derivaciones.0.centro_activo', false);
    }

    public function test_la_consulta_publica_no_envia_al_joven_a_un_centro_desactivado(): void
    {
        $solicitud = $this->crearSolicitud('ALTO', 'DERIVADA');
        $this->derivar($solicitud);

        $this->getJson("/api/consulta/{$solicitud->codigo}")->assertJsonPath('data.centro', 'Centro A');

        $this->centro->update(['activo' => false]);

        $this->getJson("/api/consulta/{$solicitud->codigo}")
            ->assertJsonPath('data.estado', 'DERIVADA')
            ->assertJsonPath('data.centro', null);
    }

    public function test_un_filtro_de_distrito_con_forma_de_lista_no_provoca_error(): void
    {
        $this->actingAs($this->joven)->getJson('/api/centros?distrito[]=Arequipa')->assertOk();
        $this->actingAs($this->joven)->getJson('/api/centros?distrito=arequipa')
            ->assertOk()
            ->assertJsonPath('data.0.nombre', 'Centro A');
    }

    private function derivar(Solicitud $solicitud): Derivacion
    {
        return Derivacion::create([
            'solicitud_id' => $solicitud->id,
            'centro_id' => $this->centro->id,
            'orientador_id' => $this->orientador->id,
            'prioridad' => 3,
            'estado' => 'PENDIENTE',
        ]);
    }

    private function crearSolicitud(string $riesgo, string $estado = 'REGISTRADA'): Solicitud
    {
        static $n = 0;
        $n++;

        return Solicitud::create([
            'codigo' => sprintf('JOV-T%04d', $n),
            'joven_id' => Joven::where('usuario_id', $this->joven->id)->value('id'),
            'motivo' => 'Ansiedad',
            'descripcion' => 'Descripción de prueba',
            'nivel_riesgo' => $riesgo,
            'canal' => 'WEB',
            'estado' => $estado,
            'derivada_113' => $riesgo === 'CRISIS',
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
