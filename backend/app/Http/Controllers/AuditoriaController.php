<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    /** Solo lectura y solo para el ADMIN: lista paginada (25 por página). */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->rol === 'ADMIN', 403);

        return response()->json(Auditoria::orderByDesc('id')->paginate(25));
    }
}