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
 * Control de acceso por ID: nadie puede editar, cancelar ni cambiar el estado
 * de un registro ajeno cambiando el número en la URL.
 * Ejecutar: php artisan test --filter=AccesoPorIdTest
 */
class AccesoPorIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_joven_no_puede_editar_la_solicitud_de_otro(): void
    {
        $solicitud = $this->crearSolicitud($this->crearJoven('duenio@prueba.pe'));
        $otro = $this->crearJoven('otro@prueba.pe');

        $this->actingAs($otro)->patchJson("/api/solicitudes/{$solicitud->id}", ['motivo' => 'Cambiado'])
            ->assertForbidden();
        $this->assertSame('Ansiedad', $solicitud->fresh()->motivo);
    }

    public function test_un_joven_no_puede_cancelar_la_solicitud_de_otro(): void
    {
        $solicitud = $this->crearSolicitud($this->crearJoven('duenio@prueba.pe'));
        $otro = $this->crearJoven('otro@prueba.pe');

        $this->actingAs($otro)->deleteJson("/api/solicitudes/{$solicitud->id}")
            ->assertForbidden();
        $this->assertSame('REGISTRADA', $solicitud->fresh()->estado);
    }

    public function test_un_centro_no_puede_aceptar_la_derivacion_de_otro_centro(): void
    {
        $centroA = $this->crearCentro('Centro A');
        $centroB = $this->crearCentro('Centro B');
        $solicitud = $this->crearSolicitud($this->crearJoven('joven@prueba.pe'), 'DERIVADA');
        $orientador = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');
        $derivacion = Derivacion::create([
            'solicitud_id' => $solicitud->id,
            'centro_id' => $centroA->id,
            'orientador_id' => $orientador->id,
            'prioridad' => 3,
            'estado' => 'PENDIENTE',
        ]);
        $cuentaCentroB = $this->crearUsuario('CENTRO', 'centro.b@prueba.pe', $centroB->id);

        $this->actingAs($cuentaCentroB)->patchJson("/api/derivaciones/{$derivacion->id}", ['estado' => 'ACEPTADA'])
            ->assertForbidden();
        $this->assertSame('PENDIENTE', $derivacion->fresh()->estado);
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

    private function crearJoven(string $correo): Usuario
    {
        $usuario = $this->crearUsuario('JOVEN', $correo);
        Joven::create(['usuario_id' => $usuario->id, 'fecha_nacimiento' => '2002-05-10', 'distrito' => 'Arequipa']);

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
            'codigo' => 'JOV-'.strtoupper(substr(md5(uniqid('', true)), 0, 5)),
            'joven_id' => $joven->id,
            'motivo' => 'Ansiedad',
            'descripcion' => 'Descripción de prueba',
            'nivel_riesgo' => 'MEDIO',
            'canal' => 'WEB',
            'estado' => $estado,
        ])->refresh();
    }
}
