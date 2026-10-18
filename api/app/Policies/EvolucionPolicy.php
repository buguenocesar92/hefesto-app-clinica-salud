<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RolClinico;
use App\Models\Evolucion;
use App\Models\Paciente;
use App\Models\Usuario;

/**
 * Permisos sobre las evoluciones: la historia clinica.
 *
 * Es la Policy que sostiene el producto. Sin autorizacion valida, escribir una
 * evolucion es falsificar informacion clinica; y leer la evolucion de otro
 * profesional sin necesidad es una filtracion.
 *
 * Aqui solo el PROFESIONAL TRATANTE puede ver y escribir. Recepcion y
 * administracion quedan afuera por definicion, no por configuracion: no hay
 * bandera que las deje entrar.
 *
 * Ver docs/03 y tests/Feature/Permisos.
 */
class EvolucionPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        // Sirve para que la ruta de listado no se rechace antes de tiempo. El
        // listado real ya viene filtrado por `verFicha` en el controlador.
        return $usuario->rol === RolClinico::Profesional && $usuario->activo;
    }

    public function view(Usuario $usuario, Evolución $evolucion): bool
    {
        return $usuario->puedeLeerFicha($evolucion->paciente);
    }

    public function create(Usuario $usuario, Paciente $paciente): bool
    {
        return $usuario->puedeRegistrarEvolucion($paciente);
    }
}