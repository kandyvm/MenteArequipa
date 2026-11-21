<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistroJovenRequest;
use App\Models\Auditoria;
use App\Models\Consentimiento;
use App\Models\Joven;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /** Intentos fallidos permitidos por cuenta, sumando todas las redes, cada 15 minutos. */
    private const MAXIMO_FALLOS_POR_CUENTA = 20;

    /** Hash bcrypt (costo 12, como BCRYPT_ROUNDS) de un texto aleatorio que nadie conoce: nunca coincide. */
    private const HASH_FICTICIO = '$2y$12$IqvIUXP5WXAIjrevr0gMUurbvTs37gR2ex7cbpvwgkqS935Z3fUVq';

    /** Inicia sesión con correo y contraseña (límite de 5 intentos por minuto en la ruta). */
    public function login(Request $request): JsonResponse
    {
        // Por compatibilidad también se acepta "contrasena"; la validación usa "password"
        if ($request->has('contrasena') && ! $request->has('password')) {
            $request->merge(['password' => $request->input('contrasena')]);
        }

        $datos = $request->validate([
            'correo' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string'],
        ]);

        // Límite por cuenta que vale para todas las redes: solo cuenta los intentos fallidos
        // (varias personas pueden entrar a la misma cuenta de prueba sin bloquearla)
        $limiteCuenta = 'login-fallos:'.mb_strtolower(trim($datos['correo']));
        if (RateLimiter::tooManyAttempts($limiteCuenta, self::MAXIMO_FALLOS_POR_CUENTA)) {
            return response()->json(['message' => 'Demasiados intentos fallidos para esta cuenta. Espera unos minutos.'], 429);
        }

        // Primero el correo tal como se escribió (cuentas antiguas con mayúsculas) y, si no, sin importar mayúsculas
        $usuario = Usuario::where('correo', $datos['correo'])->where('activo', true)->first()
            ?? Usuario::whereRaw('LOWER(correo) = ?', [mb_strtolower(trim($datos['correo']))])
                ->where('activo', true)->orderBy('id')->first();

        // Se compara siempre contra un hash (real o ficticio) para que el tiempo de
        // respuesta no revele si el correo existe. El ficticio es fijo: crearlo en cada
        // intento (Hash::make) duplicaba el tiempo y delataba los correos inexistentes.
        $hash = $usuario?->contrasena_hash ?? self::HASH_FICTICIO;
        $coincide = Hash::check($datos['password'], $hash);

        // Mensaje genérico: no revela si el correo existe
        if (! $usuario || ! $coincide) {
            RateLimiter::hit($limiteCuenta, 15 * 60);
            // Solo se registra si la cuenta existe (la auditoría apunta a una entidad real)
            if ($usuario) {
                Auditoria::registrar('INICIO_SESION_FALLIDO', 'usuario', $usuario->id);
            }

            return response()->json(['message' => 'Credenciales no válidas.'], 422);
        }

        // Una cuenta de centro no entra si su centro fue desactivado (se avisa solo con la contraseña correcta)
        if ($usuario->rol === 'CENTRO' && ! $usuario->centro?->activo) {
            return response()->json(['message' => 'El centro de esta cuenta está desactivado.'], 403);
        }

        RateLimiter::clear($limiteCuenta);
        Auth::guard('web')->login($usuario);
        $request->session()->regenerate();
        Auditoria::registrar('INICIAR_SESION', 'usuario', $usuario->id, $usuario->id);

        return response()->json(['id' => $usuario->id, 'rol' => $usuario->rol]);
    }

    /** Cierra la sesión. */
    public function logout(Request $request)
    {
        Auditoria::registrar('CERRAR_SESION', 'usuario', $request->user()->id);
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
                'version_texto' => Consentimiento::VERSION_ACTUAL,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);

            Auditoria::registrar('REGISTRAR_JOVEN', 'usuario', $usuario->id, $usuario->id);

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
