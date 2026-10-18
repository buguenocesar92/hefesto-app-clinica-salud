<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Controlador base.
 *
 * Aporta `authorize()`, que es la forma de llamar a una Policy y convertir un
 * "no" en un 403 (que el bootstrap/app.php traduce a 404 dentro de la API).
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    /**
     * Autoriza un listado que la agenda necesita y la administracion tambien.
     *
     * Profesionales, salas y tipos de atencion tienen dosenyos validos para
     * leerse: quien administra la clinica, y quien agenda. Primero se prueba el
     * permiso de administracion; si no, se exige el de agenda. Si tampoco, se
     * lanza la excepcion y el efecto es un rechazo limpio.
     */
    protected function autorizarListadoParaAgenda(string $modelo): void
    {
        $usuario = request()->user();

        if ($usuario !== null && $usuario->can('viewAny', $modelo)) {
            return;
        }

        $this->authorize('verParaAgenda', $modelo);
    }
}