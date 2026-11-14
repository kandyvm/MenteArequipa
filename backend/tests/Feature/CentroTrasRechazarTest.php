<?php

namespace Tests\Feature;

use App\Models\Centro;
use App\Models\Joven;
use App\Models\Solicitud;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Un centro deja de ver la solicitud (y su descripción) cuando rechaza la derivación
 * o el orientador la anula. Si se le vuelve a derivar, la ve de nuevo.
 * Ejecutar: php artisan test --filter=CentroTrasRechazarTest
 */
class CentroTrasRechazarTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $orientador;

    private Usuario $cuentaCentro;

    private Centro $centro;

    private Solicitud $solicitud;

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
            'descripcion' => 'Descripción privada', 'nivel_riesgo' => 'MEDIO', 'canal' => 'WEB',
            'estado' => 'EN_REVISION',
        ]);
    }

    public function test_el_centro_deja_de_ver_la_solicitud_al_rechazarla(): void
    {
        $derivacion = $this->derivar();

        $this->actingAs($this->cuentaCentro)->getJson("/api/solicitudes/{$this->solicitud->id}")
            ->assertOk()->assertJsonPath('data.descripcion', 'Descripción privada');

        $this->patchJson("/api/derivaciones/{$derivacion}", ['estado' => 'RECHAZADA'])->assertOk();

        $this->getJson("/api/solicitudes/{$this->solicitud->id}")->assertForbidden();
        $this->getJson('/api/solicitudes')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_el_centro_deja_de_ver_la_solicitud_si_el_orientador_anula_la_derivacion(): void
    {
        $derivacion = $this->derivar();

        $this->actingAs($this->orientador)->deleteJson("/api/derivaciones/{$derivacion}")->assertNoContent();

        $this->actingAs($this->cuentaCentro)->getJson("/api/solicitudes/{$this->solicitud->id}")->assertForbidden();
    }

    public function test_si_se_le_vuelve_a_derivar_la_ve_de_nuevo_solo_con_la_derivacion_vigente(): void
    {
        $primera = $this->derivar();
        $this->actingAs($this->cuentaCentro)->patchJson("/api/derivaciones/{$primera}", ['estado' => 'RECHAZADA'])->assertOk();

        $segunda = $this->derivar();

        $this->actingAs($this->cuentaCentro)->getJson("/api/solicitudes/{$this->solicitud->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.derivaciones')
            ->assertJsonPath('data.derivaciones.0.id', $segunda);
    }

    private function derivar(): int
    {
        return $this->actingAs($this->orientador)
            ->postJson("/api/solicitudes/{$this->solicitud->id}/derivaciones", ['centro_id' => $this->centro->id, 'prioridad' => 1])
            ->assertCreated()->json('data.id');
    }

    private function crearUsuario(string $rol, string $correo, ?int $centroId = null): Usuario
    {
        return Usuario::create([
            'correo' => $correo, 'contrasena_hash' => Hash::make('Prueba2026!'),
            'rol' => $rol, 'centro_id' => $centroId, 'activo' => true,
        ]);
    }
}
