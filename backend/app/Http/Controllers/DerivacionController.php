<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDerivacionRequest;
use App\Http\Requests\UpdateDerivacionRequest;
use App\Http\Resources\DerivacionResource;
use App\Models\Derivacion;
use App\Models\Seguimiento;
use App\Models\Solicitud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DerivacionController extends Controller
{
    /** Lista paginada (15 por página): el centro ve solo las suyas; orientador y admin, todas. Filtro ?estado= */
    public function index(Request $request)
    {
        $u = $request->user();
        abort_unless(in_array($u->rol, ['ORIENTADOR', 'CENTRO', 'ADMIN'], true), 403);

        $q = Derivacion::with('centro', 'solicitud');

        if ($u->rol === 'CENTRO') {
            abort_if($u->centro_id === null, 403);
            $q->where('centro_id', $u->centro_id);
        }
        if ($estado = $request->query('estado')) {
            $q->where('estado', $estado);
        }

        return DerivacionResource::collection($q->orderByDesc('id')->paginate(15));
    }

    /** El orientador deriva una solicitud EN_REVISION a un centro activo. */
    public function store(StoreDerivacionRequest $request, Solicitud $solicitud)
    {
        $datos = $request->validated();

        $derivacion = DB::transaction(function () use ($datos, $solicitud, $request) {
            // Se bloquea la fila para que dos orientadores no deriven la misma solicitud a la vez
            $solicitud = Solicitud::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();
            abort_unless($solicitud->estado === 'EN_REVISION', 409, 'La solicitud debe estar EN_REVISION para derivarla.');

            $d = Derivacion::create([
                'solicitud_id' => $solicitud->id,
                'centro_id' => $datos['centro_id'],
                'orientador_id' => $request->user()->id,
                'prioridad' => $datos['prioridad'],
                'observaciones' => isset($datos['observaciones']) ? strip_tags($datos['observaciones']) : null,
                'estado' => 'PENDIENTE',
            ]);

            $solicitud->update(['estado' => 'DERIVADA']);

            Seguimiento::create([
                'derivacion_id' => $d->id,
                'autor_id' => $request->user()->id,
                'estado_nuevo' => 'PENDIENTE',
            ]);

            return $d;
        });

        return (new DerivacionResource($derivacion->refresh()->load('centro')))
            ->response()->setStatusCode(201);
    }

    /** Detalle con su historial de seguimientos. */
    public function show(Derivacion $derivacion)
    {
        Gate::authorize('view', $derivacion);

        return new DerivacionResource($derivacion->load('centro', 'solicitud', 'seguimientos'));
    }

    /** Centro: acepta, rechaza o marca atendida. Orientador: corrige datos si está PENDIENTE. */
    public function update(UpdateDerivacionRequest $request, Derivacion $derivacion)
    {
        $datos = $request->validated();
        $u = $request->user();
        
        abort_if(
            in_array($derivacion->solicitud->estado, ['CERRADA', 'CANCELADA'], true),
            409,
            'La solicitud ya está cerrada.'
        );

        if ($u->rol === 'CENTRO') {
            $nuevo = $datos['estado'];

            $permitidos = match ($derivacion->estado) {
                'PENDIENTE' => ['ACEPTADA', 'RECHAZADA'],
                'ACEPTADA' => ['ATENDIDA'],
                default => [],
            };
            abort_unless(in_array($nuevo, $permitidos, true), 409, 'Cambio de estado no permitido.');

            DB::transaction(function () use ($derivacion, $nuevo, $datos, $u) {
                $derivacion->update([
                    'estado' => $nuevo,
                    'aceptada_en' => $nuevo === 'ACEPTADA' ? now() : $derivacion->aceptada_en,
                ]);

                // Si el centro rechaza, la solicitud vuelve a EN_REVISION para reasignarla
                $derivacion->solicitud->update([
                    'estado' => $nuevo === 'RECHAZADA' ? 'EN_REVISION' : $nuevo,
                ]);

                Seguimiento::create([
                    'derivacion_id' => $derivacion->id,
                    'autor_id' => $u->id,
                    'estado_nuevo' => $nuevo,
                    'nota' => isset($datos['nota']) ? strip_tags($datos['nota']) : null,
                ]);
            });
        } else {
            abort_unless($derivacion->estado === 'PENDIENTE', 409, 'Solo se edita una derivación PENDIENTE.');

            if (isset($datos['observaciones'])) {
                $datos['observaciones'] = strip_tags($datos['observaciones']);
            }
            $derivacion->update($datos);
        }

        return new DerivacionResource($derivacion->refresh()->load('centro', 'seguimientos'));
    }

    /** Anular: solo el orientador y solo si está PENDIENTE (409 en otro caso). */
    public function destroy(Request $request, Derivacion $derivacion)
    {
        Gate::authorize('delete', $derivacion);
        abort_unless($derivacion->estado === 'PENDIENTE', 409, 'Solo se anula una derivación PENDIENTE.');

        DB::transaction(function () use ($derivacion, $request) {
            $derivacion->update(['estado' => 'RECHAZADA']);
            $derivacion->solicitud->update(['estado' => 'EN_REVISION']);

            Seguimiento::create([
                'derivacion_id' => $derivacion->id,
                'autor_id' => $request->user()->id,
                'estado_nuevo' => 'RECHAZADA',
                'nota' => 'Derivación anulada por el orientador.',
            ]);
        });

        return response()->noContent();
    }
}
