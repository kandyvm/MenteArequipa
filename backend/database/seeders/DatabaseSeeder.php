<?php

namespace Database\Seeders;

use App\Models\Centro;
use App\Models\Consentimiento;
use App\Models\Joven;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /** Solo datos de prueba ficticios: nunca cargar información real. */
    public function run(): void
    {
        $sanAgustin = Centro::firstOrCreate(
            ['nombre' => 'Centro de Salud Mental San Agustín'],
            ['tipo' => 'MINSA', 'distrito' => 'Cerro Colorado', 'direccion' => 'Dirección de prueba 100',
             'servicios' => 'Turno mañana y tarde', 'activo' => true]
        );

        $honorio = Centro::firstOrCreate(
            ['nombre' => 'Hospital Honorio Delgado'],
            ['tipo' => 'MINSA', 'distrito' => 'Arequipa', 'direccion' => 'Dirección de prueba 200',
             'servicios' => 'Psiquiatría y psicología', 'activo' => true]
        );

        $comunitario = Centro::firstOrCreate(
            ['nombre' => 'Centro Comunitario de Prueba'],
            ['tipo' => 'ONG', 'distrito' => 'Cayma', 'direccion' => 'Dirección de prueba 300',
             'servicios' => 'Acompañamiento psicosocial', 'activo' => true]
        );

        // Contraseñas de las cuentas de prueba. Pueden definirse en Render con las variables
        // CLAVE_CUENTAS_PRUEBA (joven, orientador y centro) y CLAVE_ADMIN_PRUEBA (solo ADMIN).
        // Si no existen (por ejemplo en local o en el CI) se usa la contraseña de demostración.
        $clave = env('CLAVE_CUENTAS_PRUEBA', 'Prueba2026!');
        $claveAdmin = env('CLAVE_ADMIN_PRUEBA', $clave);

        $usuarioJoven = $this->cuentaDePrueba('joven@prueba.pe', $clave, ['rol' => 'JOVEN']);

        $joven = Joven::firstOrCreate(
            ['usuario_id' => $usuarioJoven->id],
            ['fecha_nacimiento' => '2002-05-10', 'distrito' => 'Arequipa']
        );

        Consentimiento::firstOrCreate(
            ['joven_id' => $joven->id],
            ['version_texto' => 'v1.0', 'ip' => '127.0.0.1', 'user_agent' => 'seeder']
        );

        $this->cuentaDePrueba('orientador@prueba.pe', $clave, ['rol' => 'ORIENTADOR']);

        // Antes había una sola cuenta de centro (centro@prueba.pe, de San Agustín). Se renombra para
        // conservar su id y su historial de auditoría; si la cuenta nueva ya existe, la antigua se desactiva.
        $antigua = Usuario::where('correo', 'centro@prueba.pe')->first();
        if ($antigua) {
            if (Usuario::where('correo', 'centro.sanagustin@prueba.pe')->exists()) {
                $antigua->update(['activo' => false]);
            } else {
                $antigua->update(['correo' => 'centro.sanagustin@prueba.pe']);
            }
        }

        // Una cuenta de prueba por cada centro, para poder aceptar derivaciones a cualquiera de ellos.
        $cuentasCentro = [
            'centro.sanagustin@prueba.pe' => $sanAgustin,
            'centro.honorio@prueba.pe' => $honorio,
            'centro.comunitario@prueba.pe' => $comunitario,
        ];
        foreach ($cuentasCentro as $correo => $centro) {
            $this->cuentaDePrueba($correo, $clave, ['rol' => 'CENTRO', 'centro_id' => $centro->id]);
        }

        $this->cuentaDePrueba('admin@prueba.pe', $claveAdmin, ['rol' => 'ADMIN']);
    }

    /**
     * Crea o actualiza una cuenta de prueba. La contraseña solo se vuelve a cifrar si cambió:
     * un hash nuevo en cada arranque haría que Sanctum cerrara todas las sesiones abiertas.
     */
    private function cuentaDePrueba(string $correo, string $clave, array $datos): Usuario
    {
        $usuario = Usuario::firstOrNew(['correo' => $correo]);
        $usuario->fill($datos + ['activo' => true]);

        if (! $usuario->exists || ! Hash::check($clave, $usuario->contrasena_hash)) {
            $usuario->contrasena_hash = Hash::make($clave);
        }

        $usuario->save();

        return $usuario;
    }
}
