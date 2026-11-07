<?php

namespace App\Policies;

use App\Models\Derivacion;
use App\Models\Usuario;

class DerivacionPolicy
{
    public function view(Usuario $u, Derivacion $d): bool
    {
        return match ($u->rol) {
            'ADMIN', 'ORIENTADOR' => true,
            'CENTRO' => $u->centro_id !== null && $u->centro_id === $d->centro_id,
            default => false, // el joven consulta por código, no por este endpoint
        };
    }

    public function update(Usuario $u, Derivacion $d): bool
    {
        return $u->rol === 'ORIENTADOR'
            || ($u->rol === 'CENTRO' && $u->centro_id !== null && $u->centro_id === $d->centro_id);
    }

    public function delete(Usuario $u, Derivacion $d): bool
    {
        return $u->rol === 'ORIENTADOR';
    }
}