<?php

namespace App\Providers;

use App\Models\Derivacion;
use App\Models\Solicitud;
use App\Observers\DerivacionObserver;
use App\Observers\SolicitudObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('registro', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('consulta', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
