<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\Usuario;

/**
 * Permisos de administracion de la clinica.
 *
 * Profesionales, salas, tarifas, tipos de atencion y usuarios se administran
 * igual: solo el administrador de la clinica. Se repite en cada Policy en vez
 * de inventar un rol superusuario, porque "puede administrar" es una
 * capacidad concreta, no un atajo que salta todas las comprobaciones.
 */
trait PolicyDeAdministracion
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->puedeAdministrar();
    }

    public function view(Usuario $usuario, mixed $modelo): bool
    {
        return $usuario->puedeAdministrar() && $usuario->tenant_id === $modelo->tenant_id;
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->puedeAdministrar();
    }

    public function update(Usuario $usuario, mixed $modelo): bool
    {
        return $this->view($usuario, $modelo);
    }
}