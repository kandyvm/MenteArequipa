<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Detrás de Render (Cloudflare + proxy) la IP del visitante llega en True-Client-IP.
 * Ejecutar: php artisan test --filter=IpRealTest
 */
class IpRealTest extends TestCase
{
    use RefreshDatabase;

    private const PROXY_INTERNO = '10.21.157.68';

    protected function setUp(): void
    {
        parent::setUp();

        // Ruta solo para las pruebas: devuelve la IP que ve Laravel (la misma que usa la auditoría)
        Route::get('/prueba-ip', fn (Request $r) => $r->ip());
    }

    public function test_laravel_ve_la_ip_del_visitante_y_no_la_del_proxy(): void
    {
        $this->desdeRender('203.0.113.7')->get('/prueba-ip')->assertSee('203.0.113.7');
    }

    public function test_sin_cabeceras_de_cloudflare_no_cambia_nada(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/prueba-ip')->assertSee('127.0.0.1');
    }

    public function test_cada_visitante_tiene_su_propio_limite_de_intentos(): void
    {
        $this->crearOrientador();
        $intento = ['correo' => 'orientador@prueba.pe', 'password' => 'Incorrecta1'];

        for ($i = 0; $i < 5; $i++) {
            $this->desdeRender('203.0.113.7')->postJson('/api/login', $intento)->assertStatus(422);
        }
        $this->desdeRender('203.0.113.7')->postJson('/api/login', $intento)->assertStatus(429);

        // Otra persona, detrás del mismo proxy de Render, todavía puede intentarlo
        $this->desdeRender('198.51.100.20')->postJson('/api/login', $intento)->assertStatus(422);
    }

    public function test_una_cabecera_que_no_es_una_ip_se_ignora(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::PROXY_INTERNO])
            ->withHeaders(['True-Client-IP' => 'no-es-una-ip', 'X-Forwarded-For' => '203.0.113.7'])
            ->get('/prueba-ip')->assertSee('203.0.113.7');
    }

    public function test_una_true_client_ip_inventada_no_reemplaza_a_la_de_cloudflare(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::PROXY_INTERNO])
            ->withHeaders(['CF-Connecting-IP' => '203.0.113.7', 'True-Client-IP' => '1.2.3.4'])
            ->get('/prueba-ip')->assertSee('203.0.113.7')->assertDontSee('1.2.3.4');
    }

    /** Simula lo que llega a Render: la conexión viene de un proxy interno y Cloudflare añade sus cabeceras. */
    private function desdeRender(string $ipVisitante): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::PROXY_INTERNO])
            ->withHeaders([
                'X-Forwarded-For' => "{$ipVisitante}, 104.22.17.40",
                'True-Client-IP' => $ipVisitante,
                'CF-Connecting-IP' => $ipVisitante,
            ]);
    }

    private function crearOrientador(): Usuario
    {
        return Usuario::create([
            'correo' => 'orientador@prueba.pe',
            'contrasena_hash' => Hash::make('Prueba2026!'),
            'rol' => 'ORIENTADOR',
            'activo' => true,
        ]);
    }
}
