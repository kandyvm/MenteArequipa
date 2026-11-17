<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea las cuentas desactivadas aunque conserven una sesión abierta.
 * También las cuentas CENTRO cuyo centro fue desactivado: ya no deben ver ni atender casos.
 */
class EnsureUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        $centroDesactivado = $usuario && $usuario->rol === 'CENTRO' && ! $usuario->centro?->activo;

        if ($usuario && (! $usuario->activo || $centroDesactivado)) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json([
                'message' => $centroDesactivado ? 'El centro de esta cuenta está desactivado.' : 'Cuenta desactivada.',
            ], 403);
        }

        return $next($request);
    }
}
