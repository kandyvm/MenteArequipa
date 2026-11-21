<?php

namespace Tests\Feature;

use App\Models\Joven;
use App\Models\Solicitud;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Una solicitud de CRISIS no se frena por el máximo de 3 solicitudes en curso
 * (solo por un tope de 4, para que no se use ese nivel para saltarse el límite).
 * Ejecutar: php artisan test --filter=SolicitudCrisisTest
 */
class SolicitudCrisisTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Prueba2026!';

    public function test_una_solicitud_de_crisis_se_registra_aunque_haya_tres_en_curso(): void
    {
        $joven = $this->crearJoven('joven@prueba.pe');
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($joven)->postJson('/api/solicitudes', $this->datosSolicitud('BAJO'))->assertCreated();
        }

        $this->actingAs($joven)->postJson('/api/solicitudes', $this->datosSolicitud('CRISIS'))
            ->assertCreated()
            ->assertJsonPath('data.derivada_113', true);

        // Una solicitud que no es de crisis sigue frenada
        $this->actingAs($joven)->postJson('/api/solicitudes', $this->datosSolicitud('BAJO'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ya tienes 4 solicitudes en curso. Espera a que el equipo las revise. Si estás en peligro ahora, llama a la Línea 113, opción 5.');
    }

    public function test_las_solicitudes_de_crisis_tienen_un_tope(): void
    {
        $joven = $this->crearJoven('joven@prueba.pe');
        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($joven)->postJson('/api/solicitudes', $this->datosSolicitud('CRISIS'))->assertCreated();
        }
        $this->actingAs($joven)->postJson('/api/solicitudes', $this->datosSolicitud('CRISIS'))->assertStatus(422);
        $this->assertSame(4, Solicitud::count());
    }

    private function datosSolicitud(string $riesgo): array
    {
        return [
            'motivo' => 'Ansiedad', 'descripcion' => 'Descripción de prueba',
            'nivel_riesgo' => $riesgo, 'canal' => 'WEB', 'acepta_consentimiento' => true,
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
