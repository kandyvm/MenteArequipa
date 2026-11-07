<?php

namespace App\Policies;

use App\Models\Solicitud;
use App\Models\Usuario;

class SolicitudPolicy
{
    public function viewAny(Usuario $u): bool
    {
        return true; // el alcance se filtra por rol en el controlador
    }

    public function view(Usuario $u, Solicitud $s): bool
    {
        return match ($u->rol) {
            'ADMIN', 'ORIENTADOR' => true,
            'JOVEN' => $this->esDueno($u, $s),
            'CENTRO' => $u->centro_id !== null
                && $s->derivaciones()->where('centro_id', $u->centro_id)->exists(),
            default => false,
        };
    }

    public function create(Usuario $u): bool
    {
        return $u->rol === 'JOVEN';
    }

    public function update(Usuario $u, Solicitud $s): bool
    {
        if ($u->rol === 'ORIENTADOR') {
            return true;
        }

        return $u->rol === 'JOVEN' && $this->esDueno($u, $s) && $s->estado === 'REGISTRADA';
    }

    /** Cancelar: solo el joven dueño y solo en REGISTRADA. */
    public function delete(Usuario $u, Solicitud $s): bool
    {
        return $u->rol === 'JOVEN' && $this->esDueno($u, $s) && $s->estado === 'REGISTRADA';
    }

    private function esDueno(Usuario $u, Solicitud $s): bool
    {
        return $s->joven?->usuario_id === $u->id;
    }
}