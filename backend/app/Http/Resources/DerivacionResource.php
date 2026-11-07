<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DerivacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'solicitud_id' => $this->solicitud_id,
            'solicitud' => $this->whenLoaded('solicitud', fn () => [
                'codigo' => $this->solicitud->codigo,
                'nivel_riesgo' => $this->solicitud->nivel_riesgo,
            ]),
            'centro' => $this->whenLoaded('centro', fn () => [
                'id' => $this->centro->id,
                'nombre' => $this->centro->nombre,
            ]),
            'prioridad' => $this->prioridad,
            'observaciones' => $this->observaciones,
            'estado' => $this->estado,
            'derivada_en' => $this->derivada_en,
            'aceptada_en' => $this->aceptada_en,
            'seguimientos' => $this->whenLoaded('seguimientos', fn () => $this->seguimientos->map(fn ($s) => [
                'estado_nuevo' => $s->estado_nuevo,
                'nota' => $s->nota,
                'autor_id' => $s->autor_id,
                'creado_en' => $s->creado_en,
            ])),
        ];
    }
}