<?php

namespace App\Policies;

use App\Models\Centro;
use App\Models\Usuario;

class CentroPolicy
{
    public function viewAny(Usuario $u): bool
    {
        return true;
    }

    /** Todos ven los centros activos; solo el ADMIN ve los desactivados. */
    public function view(Usuario $u, Centro $c): bool
    {
        return $u->rol === 'ADMIN' || $c->activo;
    }

    public function create(Usuario $u): bool
    {
        return $u->rol === 'ADMIN';
    }

    /** El ADMIN edita cualquiera; el CENTRO solo el suyo. */
    public function update(Usuario $u, Centro $c): bool
    {
        return $u->rol === 'ADMIN'
            || ($u->rol === 'CENTRO' && $u->centro_id !== null && $u->centro_id === $c->id);
    }

    /** "Eliminar" equivale a desactivar. */
    public function delete(Usuario $u, Centro $c): bool
    {
        return $u->rol === 'ADMIN';
    }
}