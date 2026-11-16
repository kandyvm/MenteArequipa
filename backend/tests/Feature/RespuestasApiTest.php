<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Una ruta de la API abierta sin sesión desde el navegador (sin Accept: application/json)
 * debe responder 401 en JSON, no un error 500.
 * Ejecutar: php artisan test --filter=RespuestasApiTest
 */
class RespuestasApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_ruta_protegida_abierta_en_el_navegador_responde_401_en_json(): void
    {
        $this->get('/api/solicitudes')
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_una_ruta_publica_de_la_api_sigue_respondiendo_en_json(): void
    {
        $this->get('/api/consulta/JOV-ZZZZZ')
            ->assertNotFound()
            ->assertJson(['message' => 'Código no encontrado.']);
    }
}
