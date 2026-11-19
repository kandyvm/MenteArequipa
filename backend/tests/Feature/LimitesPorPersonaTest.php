<?php

namespace Tests\Feature;

use App\Models\Centro;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Los límites de intentos no bloquean a varias personas que comparten la misma red,
 * el panel no se bloquea al usar "Ver estado" y un centro desactivado recibe el motivo real.
 * Ejecutar: php artisan test --filter=LimitesPorPersonaTest
 */
class LimitesPorPersonaTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Prueba2026!';

    private const FRONTEND = ['Origin' => 'http://localhost:5173'];

    public function test_diez_personas_de_la_misma_red_inician_sesion_en_un_minuto(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->crearUsuario('JOVEN', "joven{$i}@prueba.pe");
        }

        for ($i = 1; $i <= 10; $i++) {
            $this->withHeaders(self::FRONTEND)
                ->postJson('/api/login', ['correo' => "joven{$i}@prueba.pe", 'password' => self::CLAVE])
                ->assertOk();
        }
    }

    public function test_quien_prueba_contrasenas_en_una_cuenta_queda_bloqueado_sin_bloquear_a_los_demas(): void
    {
        $this->crearUsuario('JOVEN', 'victima@prueba.pe');
        $this->crearUsuario('JOVEN', 'companera@prueba.pe');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['correo' => 'victima@prueba.pe', 'password' => 'Incorrecta1'])->assertStatus(422);
        }
        $this->postJson('/api/login', ['correo' => 'victima@prueba.pe', 'password' => self::CLAVE])->assertStatus(429);
        // Mayúsculas o espacios no sirven para saltarse el contador
        $this->postJson('/api/login', ['correo' => ' Victima@Prueba.pe', 'password' => self::CLAVE])->assertStatus(429);

        // Otra persona desde la misma red entra sin problema
        $this->withHeaders(self::FRONTEND)
            ->postJson('/api/login', ['correo' => 'companera@prueba.pe', 'password' => self::CLAVE])
            ->assertOk();
    }

    public function test_un_correo_en_forma_de_lista_no_provoca_un_error(): void
    {
        $this->postJson('/api/login', ['correo' => ['x@prueba.pe'], 'password' => 'Incorrecta1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('correo');
    }

    public function test_una_misma_ip_que_prueba_muchas_cuentas_queda_bloqueada(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->postJson('/api/login', ['correo' => "nadie{$i}@prueba.pe", 'password' => 'Incorrecta1'])->assertStatus(422);
        }
        $this->postJson('/api/login', ['correo' => 'otro@prueba.pe', 'password' => 'Incorrecta1'])->assertStatus(429);
    }

    public function test_el_orientador_puede_ver_el_estado_de_muchos_casos_seguidos(): void
    {
        $orientador = $this->crearUsuario('ORIENTADOR', 'orientador@prueba.pe');

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($orientador)->getJson('/api/consulta/JOV-ZZZZZ')->assertNotFound();
        }
    }

    public function test_un_joven_con_sesion_no_puede_probar_mas_de_10_codigos_por_minuto(): void
    {
        $joven = $this->crearUsuario('JOVEN', 'joven@prueba.pe');

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($joven)->getJson('/api/consulta/JOV-ZZZZZ')->assertNotFound();
        }
        $this->actingAs($joven)->getJson('/api/consulta/JOV-ZZZZZ')->assertStatus(429);
    }

    public function test_sin_sesion_la_consulta_sigue_limitada_a_10_por_minuto(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/consulta/JOV-ZZZZZ')->assertNotFound();
        }
        $this->getJson('/api/consulta/JOV-ZZZZZ')->assertStatus(429);
    }

    public function test_un_centro_desactivado_con_sesion_abierta_recibe_el_motivo_real(): void
    {
        $centro = Centro::create([
            'nombre' => 'Centro A', 'tipo' => 'MINSA', 'distrito' => 'Arequipa',
            'direccion' => 'Dirección de prueba 100', 'activo' => true,
        ]);
        $cuenta = $this->crearUsuario('CENTRO', 'centro@prueba.pe', $centro->id);
        $centro->update(['activo' => false]);

        $this->actingAs($cuenta)->getJson('/api/solicitudes')
            ->assertForbidden()
            ->assertJsonPath('message', 'El centro de esta cuenta está desactivado.')
            ->assertJsonPath('cuenta_desactivada', true);
    }

    private function crearUsuario(string $rol, string $correo, ?int $centroId = null): Usuario
    {
        return Usuario::create([
            'correo' => $correo,
            'contrasena_hash' => Hash::make(self::CLAVE),
            'rol' => $rol,
            'centro_id' => $centroId,
            'activo' => true,
        ]);
    }
}
