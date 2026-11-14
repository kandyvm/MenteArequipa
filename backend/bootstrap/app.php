<?php

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
        'activo' => \App\Http\Middleware\EnsureUsuarioActivo::class,
    ]);

    // Antes que todo lo demás: la IP real del visitante (auditoría y límite de intentos)
    $middleware->prepend(\App\Http\Middleware\IpRealDelCliente::class);

    // Cabeceras de seguridad en todas las respuestas del backend
    $middleware->append(\App\Http\Middleware\CabecerasSeguridad::class);
})
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
