<?php

namespace Tests\Feature;

use App\Models\Centro;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Una cuenta CENTRO siempre debe tener su centro (el admin no puede dejarla sin centro).
 * Ejecutar: php artisan test --filter=UsuariosCentroTest
 */
class UsuariosCentroTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_admin_no_puede_dejar_una_cuenta_de_centro_sin_centro(): void
    {
        $admin = $this->crearUsuario('ADMIN', 'admin@prueba.pe');
        $centro = $this->crearCentro('Centro A');
        $cuenta = $this->crearUsuario('CENTRO', 'centro.a@prueba.pe', $centro->id);

        $this->actingAs($admin)->patchJson("/api/usuarios/{$cuenta->id}", ['centro_id' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors('centro_id');

        $this->assertSame($centro->id, $cuenta->fresh()->centro_id);
    }

    public function test_el_admin_puede_cambiar_el_centro_de_una_cuenta_de_centro(): void
    {
        $admin = $this->crearUsuario('ADMIN', 'admin@prueba.pe');
        $centroA = $this->crearCentro('Centro A');
        $centroB = $this->crearCentro('Centro B');
        $cuenta = $this->crearUsuario('CENTRO', 'centro.a@prueba.pe', $centroA->id);

        $this->actingAs($admin)->patchJson("/api/usuarios/{$cuenta->id}", ['centro_id' => $centroB->id])
            ->assertOk();

        $this->assertSame($centroB->id, $cuenta->fresh()->centro_id);
    }

    public function test_una_cuenta_que_no_es_de_centro_no_recibe_centro(): void
    {
        $admin = $this->crearUsuario('ADMIN', 'admin@prueba.pe');
        $centro = $this->crearCentro('Centro A');
        $orientador = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');

        $this->actingAs($admin)->patchJson("/api/usuarios/{$orientador->id}", ['centro_id' => $centro->id])
            ->assertOk();

        $this->assertNull($orientador->fresh()->centro_id);
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
}
