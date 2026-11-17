<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UsuarioController extends Controller
{
    /** Lista paginada (25 por página), solo ADMIN. Filtro opcional ?rol= */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->rol === 'ADMIN', 403);

        $q = Usuario::query();
        if ($rol = $request->query('rol')) {
            $q->where('rol', $rol);
        }

        return response()->json($q->orderBy('id')->paginate(25));
    }

    /** El ADMIN crea cuentas de ORIENTADOR, CENTRO o ADMIN (el joven se registra solo). */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->rol === 'ADMIN', 403);

        $datos = $request->validate([
            'correo' => ['required', 'email', 'max:150', function ($atributo, $valor, $fallar) {
                if (Usuario::correoEnUso($valor)) {
                    $fallar('Ese correo ya está registrado.');
                }
            }],
            'password' => ['required', 'string', 'min:10'],
            'rol' => ['required', Rule::in(['ORIENTADOR', 'CENTRO', 'ADMIN'])],
            'centro_id' => ['required_if:rol,CENTRO', 'nullable', 'integer', 'exists:centros,id'],
        ]);

        $usuario = Usuario::create([
            'correo' => $datos['correo'],
            'contrasena_hash' => Hash::make($datos['password']),
            'rol' => $datos['rol'],
            'centro_id' => $datos['rol'] === 'CENTRO' ? $datos['centro_id'] : null,
            'activo' => true,
        ]);

        Auditoria::registrar('CREAR_USUARIO', 'usuario', $usuario->id);

        return response()->json(['data' => $this->formato($usuario->refresh())], 201);
    }

    /** El ADMIN ve cualquiera; los demás, solo su propia cuenta. */
    public function show(Request $request, Usuario $usuario): JsonResponse
    {
        $u = $request->user();
        abort_unless($u->rol === 'ADMIN' || $u->id === $usuario->id, 403);

        return response()->json(['data' => $this->formato($usuario)]);
    }

    /** El ADMIN edita cuentas; cada persona solo puede cambiar su propia contraseña, dando la actual. */
    public function update(Request $request, Usuario $usuario): JsonResponse
    {
        $u = $request->user();
        $esAdmin = $u->rol === 'ADMIN';
        abort_unless($esAdmin || $u->id === $usuario->id, 403);

        $esPropia = $u->id === $usuario->id;
        // En la propia cuenta se usa el mismo objeto de la sesión: así, al cambiar la contraseña,
        // la sesión guarda la clave nueva y sigue abierta (las demás sesiones sí se cierran).
        if ($esPropia) {
            $usuario = $u;
        }

        $reglas = ['password' => ['sometimes', 'string', 'min:10']];
        // Para cambiar la contraseña de la propia cuenta hay que dar la actual
        // (si alguien deja la sesión abierta, no le pueden cambiar la clave)
        if ($esPropia) {
            $reglas['password_actual'] = ['required_with:password', 'string'];
        }
        if ($esAdmin) {
            $reglas += [
                'correo' => ['sometimes', 'email', 'max:150', function ($atributo, $valor, $fallar) use ($usuario) {
                    if (Usuario::correoEnUso($valor, $usuario->id)) {
                        $fallar('Ese correo ya está registrado.');
                    }
                }],
                'activo' => ['sometimes', 'boolean'],
                'centro_id' => ['sometimes', 'nullable', 'integer', 'exists:centros,id'],
            ];
        }

        $datos = $request->validate($reglas);

        if ($esPropia && isset($datos['password'])) {
            // Máximo 5 intentos fallidos por minuto: quien encuentre una sesión abierta no puede adivinar la clave
            $limite = 'contrasena-actual:'.$usuario->id;
            abort_if(RateLimiter::tooManyAttempts($limite, 5), 429, 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.');

            if (! Hash::check($datos['password_actual'], $usuario->contrasena_hash)) {
                RateLimiter::hit($limite, 60);
                Auditoria::registrar('CAMBIO_CONTRASENA_FALLIDO', 'usuario', $usuario->id);
                throw ValidationException::withMessages(['password_actual' => 'La contraseña actual no es correcta.']);
            }
            RateLimiter::clear($limite);
        }
        unset($datos['password_actual']);

        // Una cuenta CENTRO siempre necesita su centro; las demás cuentas no tienen centro.
        if (array_key_exists('centro_id', $datos)) {
            if ($usuario->rol === 'CENTRO' && $datos['centro_id'] === null) {
                throw ValidationException::withMessages(['centro_id' => 'Una cuenta de centro necesita un centro.']);
            }
            if ($usuario->rol !== 'CENTRO') {
                unset($datos['centro_id']);
            }
        }

        if (array_key_exists('activo', $datos) && ! $datos['activo'] && $usuario->id === $u->id) {
            abort(422, 'No puedes desactivar tu propia cuenta.');
        }

        if (isset($datos['password'])) {
            $datos['contrasena_hash'] = Hash::make($datos['password']);
            unset($datos['password']);
        }

        $usuario->update($datos);
        $this->auditarCambios($usuario);

        return response()->json(['data' => $this->formato($usuario->refresh())]);
    }

    /** Desactivar sin borrar (las derivaciones y la auditoría siguen apuntando a la cuenta). */
    public function destroy(Request $request, Usuario $usuario)
    {
        abort_unless($request->user()->rol === 'ADMIN', 403);
        abort_if($usuario->id === $request->user()->id, 422, 'No puedes desactivar tu propia cuenta.');

        $usuario->update(['activo' => false]);
        Auditoria::registrar('DESACTIVAR_USUARIO', 'usuario', $usuario->id);

        return response()->noContent();
    }

    /** Una fila de auditoría por tipo de cambio (contraseña, estado, datos). */
    private function auditarCambios(Usuario $usuario): void
    {
        if ($usuario->wasChanged('contrasena_hash')) {
            Auditoria::registrar('CAMBIAR_CONTRASENA', 'usuario', $usuario->id);
        }
        if ($usuario->wasChanged('activo')) {
            Auditoria::registrar($usuario->activo ? 'REACTIVAR_USUARIO' : 'DESACTIVAR_USUARIO', 'usuario', $usuario->id);
        }
        if ($usuario->wasChanged(['correo', 'centro_id'])) {
            Auditoria::registrar('EDITAR_USUARIO', 'usuario', $usuario->id);
        }
    }

    private function formato(Usuario $x): array
    {
        return [
            'id' => $x->id,
            'correo' => $x->correo,
            'rol' => $x->rol,
            'centro_id' => $x->centro_id,
            'activo' => $x->activo,
        ];
    }
}
