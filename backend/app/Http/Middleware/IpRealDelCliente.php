<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * En Render todo pasa por Cloudflare y por el proxy de Render (y el frontend llega por una
 * regla de reescritura). Laravel toma la IP de X-Forwarded-For de derecha a izquierda, así
 * que acaba viendo la IP de un proxy: la auditoría guarda una IP interna y todos los usuarios
 * comparten el mismo límite de intentos de login.
 *
 * Cloudflare escribe la IP del visitante en CF-Connecting-IP y la reescribe aunque el cliente
 * la envíe, así que no se puede falsificar. Va primero; True-Client-IP queda solo de respaldo.
 * Si llega una IP válida, se usa como X-Forwarded-For para que $request->ip() devuelva la del
 * visitante. Sin esas cabeceras (en local) no cambia nada.
 */
class IpRealDelCliente
{
    private const CABECERAS = ['CF-Connecting-IP', 'True-Client-IP'];

    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.depurar_ip') && $request->is('api/login')) {
            Log::info('Cabeceras de IP en el login', [
                'remote_addr' => $request->server('REMOTE_ADDR'),
                'x_forwarded_for' => $request->header('X-Forwarded-For'),
                'true_client_ip' => $request->header('True-Client-IP'),
                'cf_connecting_ip' => $request->header('CF-Connecting-IP'),
                'x_real_ip' => $request->header('X-Real-IP'),
            ]);
        }

        foreach (self::CABECERAS as $cabecera) {
            $ip = trim((string) $request->header($cabecera));
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $request->headers->set('X-Forwarded-For', $ip);
                break;
            }
        }

        return $next($request);
    }
}
