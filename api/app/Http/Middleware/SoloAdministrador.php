<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Administracion de la clinica.
 *
 * Corta las rutas de `/api/v1/admin` para quien no sea administrador de SU
 * clinica. La RLS ya limita que filas ve; esto limita que ACCIONES existen.
 *
 * Importante: este middleware no reemplaza a las Policies. Solo evita abrir
 * cinco rutas que solo el administrador puede usar; el detalle fino lo decide
 * cada Policy.
 */
class SoloAdministrador
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = $peticion->user();

        if ($usuario === null || ! $usuario->puedeAdministrar()) {
            abort(403, 'Solo la administracion de la clinica puede hacer esto.');
        }

        return $siguiente($peticion);
    }
}