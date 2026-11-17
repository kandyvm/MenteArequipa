<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cada ruta pública tiene su propio límite de intentos, y el registro solo acepta
 * distritos de la lista.
 * Ejecutar: php artisan test --filter=LimitesYRegistroTest
 */
class LimitesYRegistroTest extends TestCase
{
    use RefreshDatabase;

    public function test_consultar_codigos_no_bloquea_el_inicio_de_sesion(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/consulta/JOV-ZZZZZ')->assertNotFound();
        }
        $this->getJson('/api/consulta/JOV-ZZZZZ')->assertStatus(429);

        // El login tiene su propio contador: responde 422 (datos incorrectos), no 429
        $this->postJson('/api/login', ['correo' => 'nadie@prueba.pe', 'password' => 'ClaveIncorrecta1'])
            ->assertStatus(422);
    }

    public function test_el_registro_no_consume_los_intentos_de_login(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/registro', [])->assertStatus(422);
        }
        $this->postJson('/api/registro', [])->assertStatus(429);

        $this->postJson('/api/login', ['correo' => 'nadie@prueba.pe', 'password' => 'ClaveIncorrecta1'])
            ->assertStatus(422);
    }

    public function test_el_registro_rechaza_un_distrito_fuera_de_la_lista(): void
    {
        $this->postJson('/api/registro', $this->datos('Distrito inventado'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('distrito');
        $this->assertDatabaseMissing('usuarios', ['correo' => 'nuevo@prueba.pe']);
    }

    public function test_el_registro_acepta_un_distrito_de_la_lista(): void
    {
        $this->postJson('/api/registro', $this->datos('José Luis Bustamante y Rivero'))->assertCreated();
        $this->assertDatabaseHas('jovenes', ['distrito' => 'José Luis Bustamante y Rivero']);
    }

    private function datos(string $distrito): array
    {
        return [
            'correo' => 'nuevo@prueba.pe',
            'password' => 'ClaveSegura2026',
            'fecha_nacimiento' => now()->subYears(20)->toDateString(),
            'distrito' => $distrito,
            'acepta_consentimiento' => true,
        ];
    }
}
