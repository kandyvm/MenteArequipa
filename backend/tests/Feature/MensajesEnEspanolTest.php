<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Los mensajes de validación salen en español, también el de "contraseña actual".
 * Ejecutar: php artisan test --filter=MensajesEnEspanolTest
 */
class MensajesEnEspanolTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_mensaje_de_contrasena_actual_obligatoria_esta_en_espanol(): void
    {
        app()->setLocale('es');
        $admin = Usuario::create([
            'correo' => 'admin@prueba.pe', 'contrasena_hash' => Hash::make('Prueba2026!'), 'rol' => 'ADMIN', 'activo' => true,
        ]);

        $this->actingAs($admin)->patchJson("/api/usuarios/{$admin->id}", ['password' => 'NuevaClave2026'])
            ->assertStatus(422)
            ->assertJsonPath('errors.password_actual.0', 'Contraseña actual es obligatorio cuando escribes contraseña.');
    }
}
