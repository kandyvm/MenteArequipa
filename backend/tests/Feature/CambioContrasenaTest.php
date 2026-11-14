<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Para cambiar la contraseña de la propia cuenta hay que dar la actual.
 * El ADMIN puede restablecer la de otra cuenta sin conocerla.
 * Ejecutar: php artisan test --filter=CambioContrasenaTest
 */
class CambioContrasenaTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Prueba2026!';

    private const NUEVA = 'NuevaClave2026!';

    public function test_no_se_cambia_la_propia_contrasena_sin_dar_la_actual(): void
    {
        $orientador = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');

        $this->actingAs($orientador)
            ->patchJson("/api/usuarios/{$orientador->id}", ['password' => self::NUEVA])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password_actual');

        $this->assertTrue(Hash::check(self::CLAVE, $orientador->fresh()->contrasena_hash));
    }

    public function test_no_se_cambia_la_propia_contrasena_con_una_actual_incorrecta(): void
    {
        $orientador = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');

        $this->actingAs($orientador)
            ->patchJson("/api/usuarios/{$orientador->id}", [
                'password_actual' => 'NoEsLaClave1',
                'password' => self::NUEVA,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password_actual');

        $this->assertTrue(Hash::check(self::CLAVE, $orientador->fresh()->contrasena_hash));
    }

    public function test_se_cambia_la_propia_contrasena_dando_la_actual(): void
    {
        $orientador = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');

        $this->actingAs($orientador)
            ->patchJson("/api/usuarios/{$orientador->id}", [
                'password_actual' => self::CLAVE,
                'password' => self::NUEVA,
            ])
            ->assertOk();

        $this->assertTrue(Hash::check(self::NUEVA, $orientador->fresh()->contrasena_hash));
    }

    public function test_el_admin_tambien_da_la_actual_para_su_propia_cuenta(): void
    {
        $admin = $this->crearUsuario('ADMIN', 'admin@prueba.pe');

        $this->actingAs($admin)
            ->patchJson("/api/usuarios/{$admin->id}", ['password' => self::NUEVA])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password_actual');
    }

    public function test_el_admin_restablece_la_contrasena_de_otra_cuenta_sin_conocerla(): void
    {
        $admin = $this->crearUsuario('ADMIN', 'admin@prueba.pe');
        $orientador = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');

        $this->actingAs($admin)
            ->patchJson("/api/usuarios/{$orientador->id}", ['password' => self::NUEVA])
            ->assertOk();

        $this->assertTrue(Hash::check(self::NUEVA, $orientador->fresh()->contrasena_hash));
    }

    public function test_el_admin_edita_su_correo_sin_dar_la_contrasena(): void
    {
        $admin = $this->crearUsuario('ADMIN', 'admin@prueba.pe');

        $this->actingAs($admin)
            ->patchJson("/api/usuarios/{$admin->id}", ['correo' => 'admin2@prueba.pe'])
            ->assertOk();

        $this->assertSame('admin2@prueba.pe', $admin->fresh()->correo);
    }

    private function crearUsuario(string $rol, string $correo): Usuario
    {
        return Usuario::create([
            'correo' => $correo,
            'contrasena_hash' => Hash::make(self::CLAVE),
            'rol' => $rol,
            'activo' => true,
        ]);
    }
}
