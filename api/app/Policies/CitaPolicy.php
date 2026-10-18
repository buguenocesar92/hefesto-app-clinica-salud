<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cita;
use App\Models\Usuario;

/**
 * Permisos sobre la agenda.
 *
 * Agendar es una capacidad de MOSTRADOR: los tres roles la tienen. Recepcion
 * es, de hecho, quien mas la usa.
 *
 * No hay Policy de lectura clinica acá: una cita muestra nombre del paciente,
 * profesional, sala y estado. El motivo de consulta se guarda en la cita como
 * nota de agenda, no como diagnostico, y por eso es visible para recepcion.
 * El diagnostico vive en la evolucion, que solo la ve el tratante.
 */
class CitaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->puedeAgendar();
    }

    public function view(Usuario $usuario, Cita $cita): bool
    {
        return $this->viewAny($usuario) && $usuario->tenant_id === $cita->tenant_id;
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->puedeAgendar();
    }

    public function update(Usuario $usuario, Cita $cita): bool
    {
        // Una cita atendida no se toca: el registro de lo que paso en la
        // consulta es informacion clinica aunque este en la agenda.
        if ($cita->estadoEsFinal()) {
            return false;
        }

        return $this->view($usuario, $cita);
    }

    /**
     * `DELETE /citas/{id}` significa CANCELAR, no borrar. En una agenda clinica
     * borrar una cita destruye evidencia de que el paciente pidio hora.
     */
    public function delete(Usuario $usuario, Cita $cita): bool
    {
        if ($cita->estadoEsFinal()) {
            return false;
        }

        return $this->view($usuario, $cita);
    }
}