<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Usuario;

use App\Models\Sala;

/**
 * Administracion de salas.
 */
class SalaPolicy
{
    use Concerns\PolicyDeAdministracion;

    /**
     * Igual que con los profesionales: agendar requiere ver las salas.
     */
    public function verParaAgenda(Usuario $usuario): bool
    {
        return $usuario->puedeAgendar();
    }
}