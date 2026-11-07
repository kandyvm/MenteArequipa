<?php

namespace App\Observers;

use App\Models\Auditoria;
use App\Models\Solicitud;

class SolicitudObserver
{
    public function created(Solicitud $s): void
    {
        Auditoria::registrar('CREAR_SOLICITUD', 'solicitud', $s->id);
    }

    public function updated(Solicitud $s): void
    {
        if (! $s->wasChanged('estado')) {
            return;
        }

        $accion = match ($s->estado) {
            'CANCELADA' => 'CANCELAR',
            'DERIVADA' => 'DERIVAR',
            default => 'CAMBIO_ESTADO',
        };

        Auditoria::registrar($accion, 'solicitud', $s->id);
    }
}