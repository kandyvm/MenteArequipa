<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las respuestas del backend llevan cabeceras de seguridad, también cuando hay error.
 */
class CabecerasSeguridadTest extends TestCase
{
    use RefreshDatabase;

    private function assertCabeceras($respuesta): void
    {
        $respuesta->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_la_raiz_y_el_health_check_llevan_cabeceras(): void
    {
        $this->assertCabeceras($this->get('/')->assertOk());
        $this->assertCabeceras($this->get('/up')->assertOk());
    }

    public function test_una_respuesta_de_la_api_lleva_cabeceras(): void
    {
        $this->assertCabeceras($this->getJson('/api/consulta/JOV-XXXXX'));
    }

    public function test_un_401_sin_sesion_tambien_lleva_cabeceras(): void
    {
        $this->assertCabeceras($this->getJson('/api/yo')->assertUnauthorized());
    }
}
