<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'motivo' => $this->motivo,
            'descripcion' => $this->descripcion,
            'nivel_riesgo' => $this->nivel_riesgo,
            'canal' => $this->canal,
            'estado' => $this->estado,
            'derivada_113' => $this->derivada_113,
            'creada_en' => $this->creada_en,
            // Solo se incluye para orientador, centro y admin (ver SolicitudController)
            'derivaciones' => $this->whenLoaded('derivaciones', fn () => $this->derivaciones->map(fn ($d) => [
                'id' => $d->id,
                'centro_id' => $d->centro_id,
                'prioridad' => $d->prioridad,
                'estado' => $d->estado,
            ])->values()),
        ];
    }
}