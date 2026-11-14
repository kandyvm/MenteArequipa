<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCentroRequest;
use App\Http\Resources\CentroResource;
use App\Models\Auditoria;
use App\Models\Centro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CentroController extends Controller
{
    /** Directorio con filtro ?distrito=. Solo el ADMIN ve los desactivados. */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Centro::class);

        $q = Centro::query();

        if ($request->user()->rol !== 'ADMIN') {
            $q->where('activo', true);
        }
        if ($distrito = $request->query('distrito')) {
            $q->whereRaw('LOWER(distrito) = ?', [mb_strtolower($distrito)]);
        }

        return CentroResource::collection($q->orderBy('nombre')->get());
    }

    public function store(StoreCentroRequest $request)
    {
        $centro = Centro::create($this->limpiar($request->validated()) + ['activo' => true])->refresh();
        Auditoria::registrar('CREAR_CENTRO', 'centro', $centro->id);

        return (new CentroResource($centro))->response()->setStatusCode(201);
    }

    public function show(Centro $centro)
    {
        Gate::authorize('view', $centro);

        return new CentroResource($centro);
    }

    /** El ADMIN edita todo; el CENTRO edita los datos de su propio centro. */
    public function update(Request $request, Centro $centro)
    {
        Gate::authorize('update', $centro);

        $reglas = [
            'nombre' => ['sometimes', 'string', 'max:150'],
            'distrito' => ['sometimes', 'string', 'max:60'],
            'direccion' => ['sometimes', 'string', 'max:200'],
            'latitud' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'servicios' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];

        // Solo el ADMIN cambia el tipo y activa o desactiva
        if ($request->user()->rol === 'ADMIN') {
            $reglas['tipo'] = ['sometimes', Rule::in(['MINSA', 'ESSALUD', 'ONG', 'PRIVADO'])];
            $reglas['activo'] = ['sometimes', 'boolean'];
        }

        $centro->update($this->limpiar($request->validate($reglas)));

        if ($centro->wasChanged('activo')) {
            Auditoria::registrar($centro->activo ? 'REACTIVAR_CENTRO' : 'DESACTIVAR_CENTRO', 'centro', $centro->id);
        }
        if ($centro->wasChanged(['nombre', 'tipo', 'distrito', 'direccion', 'latitud', 'longitud', 'servicios'])) {
            Auditoria::registrar('EDITAR_CENTRO', 'centro', $centro->id);
        }

        return new CentroResource($centro->refresh());
    }

    /** Desactivar sin borrar (las derivaciones antiguas siguen apuntando al centro). */
    public function destroy(Centro $centro)
    {
        Gate::authorize('delete', $centro);

        $centro->update(['activo' => false]);
        if ($centro->wasChanged('activo')) {
            Auditoria::registrar('DESACTIVAR_CENTRO', 'centro', $centro->id);
        }

        return response()->noContent();
    }

    /** Quita etiquetas HTML de los textos (defensa contra XSS). */
    private function limpiar(array $datos): array
    {
        foreach (['nombre', 'distrito', 'direccion', 'servicios'] as $campo) {
            if (isset($datos[$campo]) && is_string($datos[$campo])) {
                $datos[$campo] = strip_tags($datos[$campo]);
            }
        }

        return $datos;
    }
}