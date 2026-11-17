<?php

namespace Tests\Feature;

use App\Models\Centro;
use App\Models\Joven;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Cualquier rol puede cambiar su propia contraseña por la API dando la actual
 * (o la temporal que le dio el ADMIN), sin poder tocar su rol, su correo ni otras cuentas.
 * Ejecutar: php artisan test --filter=ClavePropiaTest
 */
class ClavePropiaTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'Prueba2026!';

    private const NUEVA = 'NuevaClave2026!';

    public function test_un_joven_cambia_su_propia_contrasena(): void
    {
        $joven = $this->crearJoven('joven@prueba.pe');

        $this->actingAs($joven)
            ->patchJson("/api/usuarios/{$joven->id}", ['password_actual' => self::CLAVE, 'password' => self::NUEVA])
            ->assertOk();

        $this->assertTrue(Hash::check(self::NUEVA, $joven->fresh()->contrasena_hash));
    }

    public function test_una_cuenta_de_centro_cambia_su_propia_contrasena(): void
    {
        $centro = Centro::create([
            'nombre' => 'Centro A', 'tipo' => 'MINSA', 'distrito' => 'Arequipa',
            'direccion' => 'Dirección de prueba 100', 'activo' => true,
        ]);
        $cuenta = $this->crearUsuario('CENTRO', 'centro@prueba.pe', $centro->id);

        $this->actingAs($cuenta)
            ->patchJson("/api/usuarios/{$cuenta->id}", ['password_actual' => self::CLAVE, 'password' => self::NUEVA])
            ->assertOk();

        $this->assertTrue(Hash::check(self::NUEVA, $cuenta->fresh()->contrasena_hash));
    }

    public function test_con_la_clave_temporal_del_admin_se_puede_poner_una_nueva(): void
    {
        $admin = $this->crearUsuario('ADMIN', 'admin@prueba.pe');
        $joven = $this->crearJoven('joven@prueba.pe');

        $this->actingAs($admin)
            ->patchJson("/api/usuarios/{$joven->id}", ['password' => 'Temporal2026'])
            ->assertOk();

        $this->actingAs($joven->fresh())
            ->patchJson("/api/usuarios/{$joven->id}", ['password_actual' => 'Temporal2026', 'password' => self::NUEVA])
            ->assertOk();

        $this->assertTrue(Hash::check(self::NUEVA, $joven->fresh()->contrasena_hash));
    }

    public function test_un_joven_no_puede_cambiar_su_rol_correo_ni_estado(): void
    {
        $joven = $this->crearJoven('joven@prueba.pe');

        $this->actingAs($joven)
            ->patchJson("/api/usuarios/{$joven->id}", ['rol' => 'ADMIN', 'correo' => 'otro@prueba.pe', 'activo' => false])
            ->assertOk();

        $fresco = $joven->fresh();
        $this->assertSame('JOVEN', $fresco->rol);
        $this->assertSame('joven@prueba.pe', $fresco->correo);
        $this->assertTrue($fresco->activo);
    }

    public function test_un_joven_no_puede_cambiar_la_contrasena_de_otra_cuenta(): void
    {
        $joven = $this->crearJoven('joven@prueba.pe');
        $otro = $this->crearJoven('otro@prueba.pe');

        $this->actingAs($joven)
            ->patchJson("/api/usuarios/{$otro->id}", ['password_actual' => self::CLAVE, 'password' => self::NUEVA])
            ->assertForbidden();

        $this->assertTrue(Hash::check(self::CLAVE, $otro->fresh()->contrasena_hash));
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

    private function crearJoven(string $correo): Usuario
    {
        $usuario = $this->crearUsuario('JOVEN', $correo);
        Joven::create(['usuario_id' => $usuario->id, 'fecha_nacimiento' => '2002-05-10', 'distrito' => 'Arequipa']);

        return $usuario;
    }
}
