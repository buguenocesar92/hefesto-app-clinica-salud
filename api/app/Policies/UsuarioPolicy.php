<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Usuario;

/**
 * Administracion de las cuentas de la clinica.
 *
 * OJO con una cosa: un administrador puede crear usuarios, y por lo tanto
 * crear profesionales y receptoras. NO puede crear un administrador con el
 * mismo nombre que el suyo: `crearAdministrador` se resuelve contra la sesion.
 * El bootstrap de cuentas lo hace el despliegue (docs/04), no un administrador
 * cualquiera. Ver docs/03.
 */
class UsuarioPolicy
{
    use Concerns\PolicyDeAdministracion;

    /**
     * Crear la primera cuenta de administracion de una clinica esta reservado
     * al despliegue, no a un usuario de dentro.
     */
    public function crearAdministrador(Usuario $usuario): bool
    {
        return false;
    }

    /**
     * Un administrador no puede dejar su propia cuenta sin administrador: la
     * clinica se quedaria sin quien administra.
     */
    public function desactivarUltimoAdministrador(Usuario $usuario, Usuario $objetivo): bool
    {
        return false;
    }
}