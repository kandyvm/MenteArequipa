<?php

namespace App\Providers;

use App\Models\Derivacion;
use App\Models\Solicitud;
use App\Observers\DerivacionObserver;
use App\Observers\SolicitudObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Lottery;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Solicitud::observe(SolicitudObserver::class);
        Derivacion::observe(DerivacionObserver::class);

        // Límites con nombre: login, registro y consulta pública no comparten contador.
        // Con throttle:5,1 todas usaban la misma clave por IP y, por ejemplo,
        // cinco consultas de código bloqueaban el inicio de sesión.
        //
        // Varias personas pueden compartir una IP (wifi de la universidad, de una casa o del
        // celular). Por eso el login cuenta los intentos por correo + IP (5 por minuto frena
        // a quien prueba contraseñas en una cuenta) y aparte pone un tope amplio por IP
        // (60 por minuto) para frenar a quien prueba muchas cuentas desde un mismo lugar.
        // El correo solo se usa si llega como texto (correo[]=x no debe provocar un error 500).
        RateLimiter::for('login', function (Request $request) {
            $correo = $request->input('correo');
            $correo = is_string($correo) ? mb_strtolower(trim($correo)) : '';

            return [
                Limit::perMinute(5)->by('correo:'.$correo.'|'.$request->ip()),
                Limit::perMinute(60)->by('ip:'.$request->ip()),
            ];
        });

        // Registro: tope por IP pensado para un aula entera registrándose a la vez,
        // y otro por hora para frenar la creación masiva de cuentas.
        RateLimiter::for('registro', fn (Request $request) => [
            Limit::perMinute(30)->by('min:'.$request->ip()),
            Limit::perHour(200)->by('hora:'.$request->ip()),
        ]);

        // Consulta pública: 10 por minuto por IP (evita adivinar códigos). El personal (orientador,
        // centro y admin) usa "Ver estado" con muchos casos: para ellos el contador es por usuario
        // y más amplio. Un joven sigue con 10 por minuto: crear una cuenta no sirve para probar más códigos.
        RateLimiter::for('consulta', fn (Request $request) => in_array($request->user()?->rol, ['ORIENTADOR', 'CENTRO', 'ADMIN'], true)
            ? Limit::perMinute(120)->by('usuario:'.$request->user()->id)
            : Limit::perMinute(10)->by('ip:'.$request->ip()));

        // Los contadores de intentos se guardan en la tabla cache y Laravel no borra solos los vencidos:
        // cada intento con un correo distinto dejaba filas para siempre. Al terminar 1 de cada 100
        // peticiones web se borran los vencidos. Nunca corre en consola (al desplegar, la tabla puede
        // no existir todavía) y un error aquí no afecta la respuesta.
        $this->app->terminating(function () {
            $comandoDeConsola = $this->app->runningInConsole() && ! $this->app->runningUnitTests();
            if ($comandoDeConsola || config('cache.default') !== 'database') {
                return;
            }
            Lottery::odds(1, 100)->winner(function () {
                try {
                    DB::table(config('cache.stores.database.table', 'cache'))->where('expiration', '<=', time())->delete();
                } catch (Throwable) {
                    // Si falla, se intentará en otra petición
                }
            })->choose();
        });
    }
}
