<?php

namespace Tests\Feature;

use App\Models\Joven;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Resistencia a abusos: muchos casos CRISIS falsos no tapan a uno real que ya estaba esperando,
 * y una cuenta no admite más de 20 contraseñas fallidas cada 15 minutos aunque lleguen desde muchas redes.
 * Ejecutar: php artisan test --filter=AbusoCrisisYLoginTest
 */
class AbusoCrisisYLoginTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Prueba2026!';

    private const FRONTEND = ['Origin' => 'http://localhost:5173'];

    public function test_casos_crisis_falsos_creados_despues_no_tapan_a_uno_real_que_ya_esperaba(): void
    {
        $orientador = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');
        $real = $this->crearJoven('real@prueba.pe');
        $idReal = $this->actingAs($real)->postJson('/api/solicitudes', $this->datosSolicitud('CRISIS'))
            ->assertCreated()->json('data.id');

        // Cinco cuentas falsas, cada una con el máximo de solicitudes CRISIS que permite el sistema
        for ($n = 1; $n <= 5; $n++) {
            $falsa = $this->crearJoven("falsa{$n}@prueba.pe");
            for ($i = 0; $i < 4; $i++) {
                $this->actingAs($falsa)->postJson('/api/solicitudes', $this->datosSolicitud('CRISIS'))->assertCreated();
            }
            $this->actingAs($falsa)->postJson('/api/solicitudes', $this->datosSolicitud('CRISIS'))->assertStatus(422);
        }

        $this->actingAs($orientador)->getJson('/api/solicitudes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $idReal);
    }

    public function test_dentro_de_un_mismo_riesgo_va_primero_el_que_mas_tiempo_lleva_esperando(): void
    {
        $orientador = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');
        $joven = $this->crearJoven('joven@prueba.pe');
        $antiguo = $this->actingAs($joven)->postJson('/api/solicitudes', $this->datosSolicitud('ALTO'))->json('data.id');
        $nuevo = $this->actingAs($joven)->postJson('/api/solicitudes', $this->datosSolicitud('ALTO'))->json('data.id');

        $ids = collect($this->actingAs($orientador)->getJson('/api/solicitudes')->json('data'))->pluck('id')->all();

        $this->assertSame([$antiguo, $nuevo], $ids);
    }

    public function test_una_cuenta_no_admite_mas_de_20_fallos_aunque_vengan_de_muchas_redes(): void
    {
        $this->crearUsuario('ADMIN', 'admin@prueba.pe');
        $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');

        for ($i = 1; $i <= 20; $i++) {
            $this->withHeaders(['CF-Connecting-IP' => "10.0.{$i}.1"])
                ->postJson('/api/login', ['correo' => 'admin@prueba.pe', 'password' => "Adivina{$i}"])
                ->assertStatus(422);
        }

        // Desde otra red, incluso con la clave correcta, la cuenta espera unos minutos
        $this->withHeaders(['CF-Connecting-IP' => '10.9.9.9'])
            ->postJson('/api/login', ['correo' => 'Admin@Prueba.pe', 'password' => self::CLAVE])
            ->assertStatus(429);

        // Las demás cuentas no se afectan
        $this->withHeaders(self::FRONTEND + ['CF-Connecting-IP' => '10.9.9.9'])
            ->postJson('/api/login', ['correo' => 'orientador@prueba.pe', 'password' => self::CLAVE])
            ->assertOk();
    }

    public function test_los_inicios_de_sesion_correctos_no_bloquean_una_cuenta_compartida(): void
    {
        $this->crearUsuario('JOVEN', 'joven@prueba.pe');

        for ($i = 1; $i <= 25; $i++) {
            $this->withHeaders(self::FRONTEND + ['CF-Connecting-IP' => "10.1.{$i}.1"])
                ->postJson('/api/login', ['correo' => 'joven@prueba.pe', 'password' => self::CLAVE])
                ->assertOk();
            $this->postJson('/api/logout');
        }
    }

    public function test_un_inicio_de_sesion_correcto_reinicia_el_contador_de_fallos(): void
    {
        $this->crearUsuario('JOVEN', 'joven@prueba.pe');

        for ($i = 1; $i <= 19; $i++) {
            $this->withHeaders(['CF-Connecting-IP' => "10.2.{$i}.1"])
                ->postJson('/api/login', ['correo' => 'joven@prueba.pe', 'password' => "Adivina{$i}"])->assertStatus(422);
        }
        $this->withHeaders(self::FRONTEND + ['CF-Connecting-IP' => '10.2.99.1'])
            ->postJson('/api/login', ['correo' => 'joven@prueba.pe', 'password' => self::CLAVE])->assertOk();
        $this->postJson('/api/logout');

        for ($i = 1; $i <= 19; $i++) {
            $this->withHeaders(['CF-Connecting-IP' => "10.3.{$i}.1"])
                ->postJson('/api/login', ['correo' => 'joven@prueba.pe', 'password' => "Adivina{$i}"])->assertStatus(422);
        }
        $this->withHeaders(self::FRONTEND + ['CF-Connecting-IP' => '10.3.99.1'])
            ->postJson('/api/login', ['correo' => 'joven@prueba.pe', 'password' => self::CLAVE])->assertOk();
    }

    private function datosSolicitud(string $riesgo): array
    {
        return [
            'motivo' => 'Ansiedad', 'descripcion' => 'Descripción de prueba',
            'nivel_riesgo' => $riesgo, 'canal' => 'WEB', 'acepta_consentimiento' => true,
        ];
    }

    private function crearUsuario(string $rol, string $correo): Usuario
    {
        return Usuario::create([
            'correo' => $correo, 'contrasena_hash' => Hash::make(self::CLAVE), 'rol' => $rol, 'activo' => true,
        ]);
    }

    private function crearJoven(string $correo): Usuario
    {
        $usuario = $this->crearUsuario('JOVEN', $correo);
        Joven::create(['usuario_id' => $usuario->id, 'fecha_nacimiento' => '2002-05-10', 'distrito' => 'Arequipa']);

        return $usuario;
    }
}
