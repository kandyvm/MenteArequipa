<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
use Illuminate\Http\JsonResponse;

class ConsultaPublicaController extends Controller
{
    /**
     * Consulta pública por código de seguimiento (JOV-XXXXX).
     * Devuelve solo código, estado, centro y fecha: nunca datos personales ni notas.
     */
    public function mostrar(string $codigo): JsonResponse
    {
        $solicitud = Solicitud::where('codigo', $codigo)->first();

        if (! $solicitud) {
            return response()->json(['message' => 'Código no encontrado.'], 404);
        }

        $derivacion = $solicitud->derivaciones()->with('centro')->orderByDesc('id')->first();

        // El centro solo se muestra si la solicitud está en manos de un centro
        $conCentro = in_array($solicitud->estado, ['DERIVADA', 'ACEPTADA', 'ATENDIDA'], true);
        $centro = ($conCentro && $derivacion) ? $derivacion->centro?->nombre : null;

        // Fecha de la última actualización: último seguimiento o creación de la solicitud
        $ultimo = $derivacion?->seguimientos()->reorder()->orderByDesc('id')->first();
        $fecha = $ultimo?->creado_en ?? $solicitud->creada_en;

        return response()->json([
            'data' => [
                'codigo' => $solicitud->codigo,
                'estado' => $solicitud->estado,
                'centro' => $centro,
                'actualizado_en' => $fecha?->format('Y-m-d'),
            ],
        ]);
    }
}