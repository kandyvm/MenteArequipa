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
        $claveDemo = env('CLAVE_CUENTAS_PRUEBA', 'Prueba2026!');
        $clave = Hash::make($claveDemo);
        $claveAdmin = Hash::make(env('CLAVE_ADMIN_PRUEBA', $claveDemo));

        $usuarioJoven = Usuario::updateOrCreate(
            ['correo' => 'joven@prueba.pe'],
            ['contrasena_hash' => $clave, 'rol' => 'JOVEN', 'activo' => true]
        );

        $joven = Joven::firstOrCreate(
            ['usuario_id' => $usuarioJoven->id],
            ['fecha_nacimiento' => '2002-05-10', 'distrito' => 'Arequipa']
        );

        Consentimiento::firstOrCreate(
            ['joven_id' => $joven->id],
            ['version_texto' => 'v1.0', 'ip' => '127.0.0.1', 'user_agent' => 'seeder']
        );

        Usuario::updateOrCreate(
            ['correo' => 'orientador@prueba.pe'],
            ['contrasena_hash' => $clave, 'rol' => 'ORIENTADOR', 'activo' => true]
        );

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
            Usuario::updateOrCreate(
                ['correo' => $correo],
                ['contrasena_hash' => $clave, 'rol' => 'CENTRO', 'centro_id' => $centro->id, 'activo' => true]
            );
        }

        Usuario::updateOrCreate(
            ['correo' => 'admin@prueba.pe'],
            ['contrasena_hash' => $claveAdmin, 'rol' => 'ADMIN', 'activo' => true]
        );
    }
}
