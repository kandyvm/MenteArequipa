<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSolicitudRequest;
use App\Http\Requests\UpdateSolicitudRequest;
use App\Http\Resources\SolicitudResource;
use App\Models\Joven;
use App\Models\Solicitud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SolicitudController extends Controller
{
    /** Lista paginada (15 por página) según el rol. */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Solicitud::class);
        $u = $request->user();

        $q = Solicitud::query();

        if ($u->rol === 'JOVEN') {
            $q->whereIn('joven_id', Joven::where('usuario_id', $u->id)->select('id'));
        } elseif ($u->rol === 'CENTRO') {
            $q->whereHas('derivaciones', fn ($d) => $d->where('centro_id', $u->centro_id));
        }

        if ($estado = $request->query('estado')) {
            $q->where('estado', $estado);
        }
        if ($nivel = $request->query('nivel_riesgo')) {
            $q->where('nivel_riesgo', $nivel);
        }

        return SolicitudResource::collection($q->orderByDesc('id')->paginate(15));
    }

    /** Crea una solicitud con código de seguimiento JOV-XXXXX. */
    public function store(StoreSolicitudRequest $request)
    {
        $datos = $request->validated();
        $joven = Joven::where('usuario_id', $request->user()->id)->firstOrFail();

        $solicitud = Solicitud::create([
            'codigo' => $this->generarCodigo(),
            'joven_id' => $joven->id,
            'motivo' => strip_tags($datos['motivo']),
            'descripcion' => strip_tags($datos['descripcion']),
            'nivel_riesgo' => $datos['nivel_riesgo'],
            'canal' => $datos['canal'],
            'estado' => 'REGISTRADA',
            'derivada_113' => $datos['nivel_riesgo'] === 'CRISIS',
        ])->refresh();

        return (new SolicitudResource($solicitud))->response()->setStatusCode(201);
    }

    public function show(Solicitud $solicitud)
    {
        Gate::authorize('view', $solicitud);

        return new SolicitudResource($solicitud);
    }

    /** El joven edita sus datos; el orientador cambia el estado. */
    public function update(UpdateSolicitudRequest $request, Solicitud $solicitud)
    {
        $datos = $request->validated();

        if ($request->user()->rol === 'ORIENTADOR') {
            $nuevo = $datos['estado'];
            $actual = $solicitud->estado;

            $valido = ($nuevo === 'EN_REVISION' && $actual === 'REGISTRADA')
                || ($nuevo === 'CERRADA' && ! in_array($actual, ['CERRADA', 'CANCELADA'], true));

            abort_unless($valido, 409, 'Cambio de estado no permitido.');

            $solicitud->update(['estado' => $nuevo]);
        } else {
            foreach (['motivo', 'descripcion'] as $campo) {
                if (isset($datos[$campo])) {
                    $datos[$campo] = strip_tags($datos[$campo]);
                }
            }
            if (isset($datos['nivel_riesgo'])) {
                $datos['derivada_113'] = $datos['nivel_riesgo'] === 'CRISIS';
            }
            $solicitud->update($datos);
        }

        return new SolicitudResource($solicitud->refresh());
    }

    /** Cancelar sin borrar: pasa a CANCELADA. */
    public function destroy(Solicitud $solicitud)
    {
        Gate::authorize('delete', $solicitud);

        $solicitud->update(['estado' => 'CANCELADA']);

        return response()->noContent();
    }

    /** Código aleatorio sin caracteres ambiguos (sin I, O, 0 ni 1). */
    private function generarCodigo(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $codigo = 'JOV-';
            for ($i = 0; $i < 5; $i++) {
                $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
        } while (Solicitud::where('codigo', $codigo)->exists());

        return $codigo;
    }
}