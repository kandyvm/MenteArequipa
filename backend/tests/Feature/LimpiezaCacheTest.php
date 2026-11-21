<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Lottery;
use Tests\TestCase;

/**
 * Los contadores de intentos vencidos se borran de la tabla cache y los vigentes se conservan.
 * Ejecutar: php artisan test --filter=LimpiezaCacheTest
 */
class LimpiezaCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Producción guarda la caché en la base de datos; las demás pruebas usan memoria
        config(['cache.default' => 'database']);
    }

    protected function tearDown(): void
    {
        Lottery::determineResultNormally();
        parent::tearDown();
    }

    public function test_se_borran_los_contadores_vencidos_y_se_conservan_los_vigentes(): void
    {
        $this->sembrar();
        Lottery::alwaysWin();

        $this->getJson('/api/consulta/JOV-ZZZZZ')->assertNotFound();

        $this->assertSame(0, DB::table('cache')->where('key', 'like', 'vencido%')->count());
        $this->assertSame(1, DB::table('cache')->where('key', 'vigente')->count());
    }

    public function test_la_mayoria_de_las_peticiones_no_hace_la_limpieza(): void
    {
        $this->sembrar();
        Lottery::alwaysLose();

        $this->getJson('/api/consulta/JOV-ZZZZZ')->assertNotFound();

        $this->assertSame(3, DB::table('cache')->where('key', 'like', 'vencido%')->count());
    }

    public function test_los_limites_de_intentos_siguen_funcionando_con_la_cache_en_la_base_de_datos(): void
    {
        Lottery::alwaysWin();

        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/consulta/JOV-ZZZZZ')->assertNotFound();
        }
        $this->getJson('/api/consulta/JOV-ZZZZZ')->assertStatus(429);
    }

    private function sembrar(): void
    {
        foreach (['vencido1', 'vencido2', 'vencido3'] as $clave) {
            DB::table('cache')->insert(['key' => $clave, 'value' => 'x', 'expiration' => time() - 60]);
        }
        DB::table('cache')->insert(['key' => 'vigente', 'value' => 'x', 'expiration' => time() + 600]);
    }
}
