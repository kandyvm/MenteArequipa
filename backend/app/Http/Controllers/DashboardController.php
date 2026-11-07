<?php

namespace App\Http\Controllers;

use App\Models\Derivacion;
use App\Models\Solicitud;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Indicadores del dashboard del orientador. */
    public function resumen(Request $request): JsonResponse
    {
        abort_unless($request->user()->rol === 'ORIENTADOR', 403);

        return response()->json([
            'data' => [
                'solicitudes_nuevas' => Solicitud::where('estado', 'REGISTRADA')->count(),
                'derivaciones_en_curso' => Derivacion::whereIn('estado', ['PENDIENTE', 'ACEPTADA'])->count(),
                'casos_atendidos' => Solicitud::where('estado', 'ATENDIDA')->count(),
                'derivaciones_113' => Solicitud::where('derivada_113', true)->count(),
            ],
        ]);
    }
}