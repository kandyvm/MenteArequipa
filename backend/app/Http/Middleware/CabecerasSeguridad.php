<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad en todas las respuestas del backend (API, / y /up).
 * El sitio estático ya las envía con su propia configuración en Render; aquí se agregan
 * a las respuestas de la API para que no dependan de esa configuración.
 */
class CabecerasSeguridad
{
    private const CABECERAS = [
        // El navegador no intenta adivinar el tipo de contenido (evita tratar JSON como HTML)
        'X-Content-Type-Options' => 'nosniff',
        // Ninguna página puede mostrar estas respuestas dentro de un iframe
        'X-Frame-Options' => 'DENY',
        // A otros sitios solo se les envía el dominio de origen, no la ruta completa
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        foreach (self::CABECERAS as $nombre => $valor) {
            if (! $respuesta->headers->has($nombre)) {
                $respuesta->headers->set($nombre, $valor);
            }
        }
        // No revelar la versión exacta de PHP (la cabecera la añade PHP, no Laravel)
        header_remove('X-Powered-By');
        return $respuesta;
    }
}
