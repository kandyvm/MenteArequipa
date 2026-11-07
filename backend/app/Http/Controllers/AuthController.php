<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistroJovenRequest;
use App\Models\Consentimiento;
use App\Models\Joven;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /** Inicia sesión con correo y contraseña (límite de 5 intentos por minuto en la ruta). */
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'correo' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string'],
        ]);

        $usuario = Usuario::where('correo', $datos['correo'])->where('activo', true)->first();

        // Se compara siempre contra un hash (real o ficticio) para que el tiempo de
        // respuesta no revele si el correo existe
        $hash = $usuario?->contrasena_hash ?? Hash::make('contrasena-ficticia');
        $coincide = Hash::check($datos['password'], $hash);

        // Mensaje genérico: no revela si el correo existe
        if (! $usuario || ! $coincide) {
            return response()->json(['message' => 'Credenciales no válidas.'], 422);
        }

        Auth::guard('web')->login($usuario);
        $request->session()->regenerate();

        return response()->json(['id' => $usuario->id, 'rol' => $usuario->rol]);
    }

    /** Cierra la sesión. */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /** Registra un joven (18 a 29 años) con su consentimiento informado. */
    public function registro(RegistroJovenRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $usuario = DB::transaction(function () use ($datos, $request) {
            $usuario = Usuario::create([
                'correo' => $datos['correo'],
                'contrasena_hash' => Hash::make($datos['password']),
                'rol' => 'JOVEN',
            ]);

            $joven = Joven::create([
                'usuario_id' => $usuario->id,
                'fecha_nacimiento' => $datos['fecha_nacimiento'],
                'distrito' => strip_tags($datos['distrito']),
                'telefono' => $datos['telefono'] ?? null,
            ]);

            Consentimiento::create([
                'joven_id' => $joven->id,
                'version_texto' => 'v1.0',
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);

            return $usuario;
        });

        return response()->json(['id' => $usuario->id, 'rol' => $usuario->rol], 201);
    }

    /** Devuelve el usuario con sesión activa. */
    public function yo(Request $request): JsonResponse
    {
        $u = $request->user();

        return response()->json(['id' => $u->id, 'correo' => $u->correo, 'rol' => $u->rol, 'centro_id' => $u->centro_id]);
    }
}