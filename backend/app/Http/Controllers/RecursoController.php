<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Recurso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecursoController extends Controller
{
    /** El ADMIN ve todos; los demás roles, solo los publicados. */
    public function index(Request $request): JsonResponse
    {
        $q = Recurso::query();
        if ($request->user()->rol !== 'ADMIN') {
            $q->where('publicado', true);
        }

        return response()->json(['data' => $q->orderBy('titulo')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->rol === 'ADMIN', 403);

        $recurso = Recurso::create($this->limpiar($request->validate($this->reglas(true))))->refresh();
        Auditoria::registrar('CREAR_RECURSO', 'recurso', $recurso->id);

        return response()->json(['data' => $recurso], 201);
    }

    public function show(Request $request, Recurso $recurso): JsonResponse
    {
        abort_unless($request->user()->rol === 'ADMIN' || $recurso->publicado, 403);

        return response()->json(['data' => $recurso]);
    }

    public function update(Request $request, Recurso $recurso): JsonResponse
    {
        abort_unless($request->user()->rol === 'ADMIN', 403);

        $recurso->update($this->limpiar($request->validate($this->reglas(false))));

        if ($recurso->wasChanged('publicado')) {
            Auditoria::registrar($recurso->publicado ? 'PUBLICAR_RECURSO' : 'OCULTAR_RECURSO', 'recurso', $recurso->id);
        }
        if ($recurso->wasChanged(['titulo', 'tipo', 'url'])) {
            Auditoria::registrar('EDITAR_RECURSO', 'recurso', $recurso->id);
        }

        return response()->json(['data' => $recurso->refresh()]);
    }

    public function destroy(Request $request, Recurso $recurso)
    {
        abort_unless($request->user()->rol === 'ADMIN', 403);

        $id = $recurso->id;
        $recurso->delete();
        Auditoria::registrar('ELIMINAR_RECURSO', 'recurso', $id);

        return response()->noContent();
    }

    private function reglas(bool $crear): array
    {
        $req = $crear ? 'required' : 'sometimes';

        return [
            'titulo' => [$req, 'string', 'max:150'],
            'tipo' => [$req, Rule::in(['GUIA', 'ENLACE', 'PDF'])],
            // Solo http/https: evita enlaces tipo javascript: (XSS)
            'url' => [$req, 'url:http,https', 'max:255'],
            'publicado' => ['sometimes', 'boolean'],
        ];
    }

    private function limpiar(array $datos): array
    {
        if (isset($datos['titulo'])) {
            $datos['titulo'] = strip_tags($datos['titulo']);
        }

        return $datos;
    }
}