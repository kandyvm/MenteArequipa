<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSolicitudRequest;
use App\Http\Requests\UpdateSolicitudRequest;
use App\Http\Resources\SolicitudResource;
use App\Models\Consentimiento;
use App\Models\Joven;
use App\Models\Solicitud;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SolicitudController extends Controller
{
    /** Lista paginada (15 por página) según el rol. */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Solicitud::class);
        $u = $request->user();

        $q = Solicitud::query()->with($this->relacionDerivaciones($u));

        if ($u->rol === 'JOVEN') {
            $q->whereIn('joven_id', Joven::where('usuario_id', $u->id)->select('id'));
        } elseif ($u->rol === 'CENTRO') {
            $q->whereHas('derivaciones', fn ($d) => $d->vigentesParaCentro($u->centro_id));
        }

        if ($estado = $request->query('estado')) {
            $q->where('estado', $estado);
        }
        if ($nivel = $request->query('nivel_riesgo')) {
            $q->where('nivel_riesgo', $nivel);
        }

        // Orientador, centro y admin: primero los casos que esperan atención y, entre ellos, los de mayor
        // riesgo (CRISIS, ALTO, MEDIO, BAJO), para que un caso en crisis no quede en otra página.
        // El joven ve sus solicitudes por fecha, como siempre.
        if ($u->rol !== 'JOVEN') {
            $q->orderByRaw("CASE WHEN estado IN ('CERRADA', 'CANCELADA') THEN 2 WHEN estado = 'ATENDIDA' THEN 1 ELSE 0 END")
                ->orderByRaw("CASE nivel_riesgo WHEN 'CRISIS' THEN 0 WHEN 'ALTO' THEN 1 WHEN 'MEDIO' THEN 2 ELSE 3 END");
        }

        return SolicitudResource::collection($q->orderByDesc('id')->paginate(15));
    }

    /** Crea una solicitud con código de seguimiento JOV-XXXXX. */
    public function store(StoreSolicitudRequest $request)
    {
        $datos = $request->validated();
        $joven = Joven::where('usuario_id', $request->user()->id)->firstOrFail();

        // El consentimiento y la solicitud se guardan juntos o no se guarda nada
        $solicitud = DB::transaction(function () use ($datos, $joven, $request) {
            Consentimiento::create([
                'joven_id' => $joven->id,
                'version_texto' => Consentimiento::VERSION_ACTUAL,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);

            return Solicitud::create([
                'codigo' => $this->generarCodigo(),
                'joven_id' => $joven->id,
                'motivo' => strip_tags($datos['motivo']),
                'descripcion' => strip_tags($datos['descripcion']),
                'nivel_riesgo' => $datos['nivel_riesgo'],
                'canal' => $datos['canal'],
                'estado' => 'REGISTRADA',
                'derivada_113' => $datos['nivel_riesgo'] === 'CRISIS',
            ])->refresh();
        });

        return (new SolicitudResource($solicitud))->response()->setStatusCode(201);
    }

    public function show(Request $request, Solicitud $solicitud)
    {
        Gate::authorize('view', $solicitud);

        return new SolicitudResource($solicitud->load($this->relacionDerivaciones($request->user())));
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

            // No se cierra una solicitud mientras un centro tenga una derivación activa
            abort_if(
                $nuevo === 'CERRADA' && $solicitud->derivaciones()->whereIn('estado', ['PENDIENTE', 'ACEPTADA'])->exists(),
                409,
                'Hay una derivación activa: anúlala o espera a que el centro la atienda.'
            );

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

        return new SolicitudResource(
            $solicitud->refresh()->load($this->relacionDerivaciones($request->user()))
        );
    }

    /** Cancelar sin borrar: pasa a CANCELADA. */
    public function destroy(Solicitud $solicitud)
    {
        Gate::authorize('delete', $solicitud);

        $solicitud->update(['estado' => 'CANCELADA']);

        return response()->noContent();
    }

    /**
     * Derivaciones que puede ver cada rol dentro de una solicitud: el orientador y el
     * admin ven todas, el centro solo las suyas y el joven ninguna (consulta por código).
     */
    private function relacionDerivaciones(Usuario $u): array
    {
        if ($u->rol === 'JOVEN') {
            return [];
        }

        return ['derivaciones' => function ($d) use ($u) {
            if ($u->rol === 'CENTRO') {
                $d->vigentesParaCentro($u->centro_id);
            }
            // El centro se carga para saber si sigue activo (el orientador debe reasignar si no)
            $d->with('centro:id,activo')->orderBy('id');
        }];
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
