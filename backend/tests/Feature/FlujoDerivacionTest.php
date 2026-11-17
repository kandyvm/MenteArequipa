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
 * Un caso no debe quedar bloqueado: si el centro aceptó pero no atiende, el orientador
 * puede anular la derivación, volver a derivar o cerrar el caso.
 * Ejecutar: php artisan test --filter=FlujoDerivacionTest
 */
class FlujoDerivacionTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $orientador;

    private Usuario $cuentaCentro;

    private Centro $centro;

    private Solicitud $solicitud;

    private Derivacion $derivacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centro = Centro::create([
            'nombre' => 'Centro A', 'tipo' => 'MINSA', 'distrito' => 'Arequipa',
            'direccion' => 'Dirección de prueba 100', 'activo' => true,
        ]);
        $this->orientador = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');
        $this->cuentaCentro = $this->crearUsuario('CENTRO', 'centro@prueba.pe', $this->centro->id);

        $usuarioJoven = $this->crearUsuario('JOVEN', 'joven@prueba.pe');
        $joven = Joven::create(['usuario_id' => $usuarioJoven->id, 'fecha_nacimiento' => '2002-05-10', 'distrito' => 'Arequipa']);
        $this->solicitud = Solicitud::create([
            'codigo' => 'JOV-ABCDE', 'joven_id' => $joven->id, 'motivo' => 'Ansiedad',
            'descripcion' => 'Descripción de prueba', 'nivel_riesgo' => 'ALTO', 'canal' => 'WEB',
            'estado' => 'EN_REVISION',
        ]);

        $id = $this->actingAs($this->orientador)
            ->postJson("/api/solicitudes/{$this->solicitud->id}/derivaciones", ['centro_id' => $this->centro->id, 'prioridad' => 5])
            ->assertCreated()->json('data.id');
        $this->derivacion = Derivacion::findOrFail($id);
    }

    public function test_el_orientador_puede_anular_una_derivacion_aceptada_y_cerrar_el_caso(): void
    {
        $this->actingAs($this->cuentaCentro)->patchJson("/api/derivaciones/{$this->derivacion->id}", ['estado' => 'ACEPTADA'])->assertOk();

        $this->actingAs($this->orientador)->deleteJson("/api/derivaciones/{$this->derivacion->id}")->assertNoContent();
        $this->assertSame('RECHAZADA', $this->derivacion->fresh()->estado);
        $this->assertSame('EN_REVISION', $this->solicitud->fresh()->estado);

        $this->actingAs($this->orientador)->patchJson("/api/solicitudes/{$this->solicitud->id}", ['estado' => 'CERRADA'])->assertOk();
        $this->assertSame('CERRADA', $this->solicitud->fresh()->estado);
    }

    public function test_tras_anular_una_aceptada_se_puede_volver_a_derivar(): void
    {
        $this->actingAs($this->cuentaCentro)->patchJson("/api/derivaciones/{$this->derivacion->id}", ['estado' => 'ACEPTADA'])->assertOk();
        $this->actingAs($this->orientador)->deleteJson("/api/derivaciones/{$this->derivacion->id}")->assertNoContent();

        $this->actingAs($this->orientador)
            ->postJson("/api/solicitudes/{$this->solicitud->id}/derivaciones", ['centro_id' => $this->centro->id, 'prioridad' => 3])
            ->assertCreated();
        $this->assertSame('DERIVADA', $this->solicitud->fresh()->estado);
    }

    public function test_no_se_puede_anular_una_derivacion_ya_atendida(): void
    {
        $this->actingAs($this->cuentaCentro)->patchJson("/api/derivaciones/{$this->derivacion->id}", ['estado' => 'ACEPTADA'])->assertOk();
        $this->actingAs($this->cuentaCentro)->patchJson("/api/derivaciones/{$this->derivacion->id}", ['estado' => 'ATENDIDA'])->assertOk();

        $this->actingAs($this->orientador)->deleteJson("/api/derivaciones/{$this->derivacion->id}")->assertStatus(409);
        $this->assertSame('ATENDIDA', $this->derivacion->fresh()->estado);
    }

    public function test_el_centro_no_puede_anular_derivaciones(): void
    {
        $this->actingAs($this->cuentaCentro)->deleteJson("/api/derivaciones/{$this->derivacion->id}")->assertForbidden();
        $this->assertSame('PENDIENTE', $this->derivacion->fresh()->estado);
    }

    public function test_un_caso_atendido_sigue_contando_despues_de_cerrarse(): void
    {
        $this->actingAs($this->cuentaCentro)->patchJson("/api/derivaciones/{$this->derivacion->id}", ['estado' => 'ACEPTADA'])->assertOk();
        $this->actingAs($this->cuentaCentro)->patchJson("/api/derivaciones/{$this->derivacion->id}", ['estado' => 'ATENDIDA'])->assertOk();
        $this->actingAs($this->orientador)->getJson('/api/dashboard/resumen')->assertJsonPath('data.casos_atendidos', 1);

        $this->actingAs($this->orientador)->patchJson("/api/solicitudes/{$this->solicitud->id}", ['estado' => 'CERRADA'])->assertOk();
        $this->actingAs($this->orientador)->getJson('/api/dashboard/resumen')->assertJsonPath('data.casos_atendidos', 1);
    }

    private function crearUsuario(string $rol, string $correo, ?int $centroId = null): Usuario
    {
        return Usuario::create([
            'correo' => $correo,
            'contrasena_hash' => Hash::make('Prueba2026!'),
            'rol' => $rol,
            'centro_id' => $centroId,
            'activo' => true,
        ]);
    }
}
