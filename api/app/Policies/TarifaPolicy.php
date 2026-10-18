<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tarifa;

/**
 * Administracion del tarifario.
 */
class TarifaPolicy
{
    use Concerns\PolicyDeAdministracion;
}