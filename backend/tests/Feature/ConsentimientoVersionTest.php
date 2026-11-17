<?php

namespace Tests\Feature;

use App\Models\Consentimiento;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El consentimiento se guarda con la versión del texto que se muestra (v1.1: incluye el contacto ARCO).
 * Ejecutar: php artisan test --filter=ConsentimientoVersionTest
 */
class ConsentimientoVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_version_actual_es_la_1_1(): void
    {
        $this->assertSame('v1.1', Consentimiento::VERSION_ACTUAL);
    }

    public function test_el_registro_y_cada_solicitud_guardan_la_version_actual(): void
    {
        $this->postJson('/api/registro', [
            'correo' => 'nuevo@prueba.pe',
            'password' => 'ClaveSegura2026',
            'fecha_nacimiento' => now()->subYears(20)->toDateString(),
            'distrito' => 'Cayma',
            'acepta_consentimiento' => true,
        ])->assertCreated();

        $joven = Usuario::where('correo', 'nuevo@prueba.pe')->firstOrFail();
        $this->actingAs($joven)->postJson('/api/solicitudes', [
            'motivo' => 'Ansiedad',
            'descripcion' => 'Descripción de prueba',
            'nivel_riesgo' => 'BAJO',
            'canal' => 'WEB',
            'acepta_consentimiento' => true,
        ])->assertCreated();

        $this->assertSame(2, Consentimiento::count());
        $this->assertSame(2, Consentimiento::where('version_texto', 'v1.1')->count());
    }
}
