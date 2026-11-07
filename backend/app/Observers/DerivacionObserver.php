<?php

namespace App\Observers;

use App\Models\Auditoria;
use App\Models\Derivacion;

class DerivacionObserver
{
    public function updated(Derivacion $d): void
    {
        if ($d->wasChanged('estado')) {
            Auditoria::registrar('CAMBIO_ESTADO', 'derivacion', $d->id);
        }
    }
}