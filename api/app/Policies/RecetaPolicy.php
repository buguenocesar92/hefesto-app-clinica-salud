<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EstadoReceta;
use App\Enums\RolClinico;
use App\Models\Paciente;
use App\Models\Receta;
use App\Models\Usuario;

/**
 * Permisos sobre recetas y certificados.
 *
 * Emitir una receta es una accion clinica: solo el tratante. Verla o
 * imprimirla tambien, porque el papel sale de la pantalla y del hospital.
 */
class RecetaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->rol === RolClinico::Profesional && $usuario->activo;
    }

    public function view(Usuario $usuario, Receta $receta): bool
    {
        return $usuario->puedeLeerFicha($receta->paciente);
    }

    public function create(Usuario $usuario, Paciente $paciente): bool
    {
        return $usuario->puedeEmitirReceta($paciente);
    }

    /**
     * Anular una receta.
     *
     * Solo el mismo profesional que la emitio, y solo mientras no este anulada.
     * Una receta anulada no se revive.
     */
    public function anular(Usuario $usuario, Receta $receta): bool
    {
        if ($receta->estado === EstadoReceta::Anulada) {
            return false;
        }

        return $usuario->esProfesional()
            && $usuario->profesional_id === $receta->profesional_id
            && $this->view($usuario, $receta);
    }
}