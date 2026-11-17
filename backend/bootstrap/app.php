<?php

use App\Http\Middleware\CabecerasSeguridad;
use App\Http\Middleware\EnsureUsuarioActivo;
use App\Http\Middleware\IpRealDelCliente;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();

        // Render (con Cloudflare delante) actúa como proxy; la IP del visitante la fija antes IpRealDelCliente.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'activo' => EnsureUsuarioActivo::class,
        ]);

        // Antes que todo lo demás: la IP real del visitante (auditoría y límite de intentos)
        $middleware->prepend(IpRealDelCliente::class);

        // Cabeceras de seguridad en todas las respuestas del backend
        $middleware->append(CabecerasSeguridad::class);

        // No hay pantalla de login en el backend (es una API): sin sesión se responde 401, no se redirige
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Las rutas /api siempre responden en JSON (por ejemplo, 401 sin sesión), aunque se abran en el navegador.
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());
    })->create();
