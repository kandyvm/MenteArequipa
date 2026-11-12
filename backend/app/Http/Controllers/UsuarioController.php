<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'correo' => ['required', 'email', 'max:150', 'unique:usuarios,correo'],
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

    /** El ADMIN edita cuentas; cada persona solo puede cambiar su propia contraseña. */
    public function update(Request $request, Usuario $usuario): JsonResponse
    {
        $u = $request->user();
        $esAdmin = $u->rol === 'ADMIN';
        abort_unless($esAdmin || $u->id === $usuario->id, 403);

        $reglas = ['password' => ['sometimes', 'string', 'min:10']];
        if ($esAdmin) {
            $reglas += [
                'correo' => ['sometimes', 'email', 'max:150', Rule::unique('usuarios', 'correo')->ignore($usuario->id)],
                'activo' => ['sometimes', 'boolean'],
                'centro_id' => ['sometimes', 'nullable', 'integer', 'exists:centros,id'],
            ];
        }

        $datos = $request->validate($reglas);

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