<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Usuario;

use App\Models\TipoAtencion;

/**
 * Administracion de tipos de atencion (y su duracion).
 */
class TipoAtencionPolicy
{
    use Concerns\PolicyDeAdministracion;

    /**
     * Agendar necesita conocer la duracion de cada tipo de atencion.
     */
    public function verParaAgenda(Usuario $usuario): bool
    {
        return $usuario->puedeAgendar();
    }
}