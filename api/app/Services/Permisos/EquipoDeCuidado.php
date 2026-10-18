<?php

declare(strict_types=1);

namespace App\Services\Permisos;

use App\Models\AsignacionCuidado;
use App\Models\Cita;
use App\Models\Evolucion;
use App\Models\Paciente;
use App\Models\Usuario;

/**
 * Responde si un profesional trata a un paciente concreto.
 *
 * Esta es la pregunta que separa "puede agendar" de "puede leer historia
 * clinica". Un profesional de la clinica NO lee la ficha de todos los
 * pacientes de la clinica: solo de aquellos que trata.
 *
 * TRES FORMAS DE SER TRATANTE
 * ---------------------------
 *  1. Tiene una asignacion de cuidado registrada por la clinica (tabla
 *     `asignaciones_cuidado`). Es la forma administrativa, para cuando hay un
 *     equipo de salud definido.
 *  2. Tiene alguna cita con ese paciente, en cualquier estado. Agendarla ya
 *     significa que el paciente entro en su agenda.
 *  3. Registro una evolucion para ese paciente antes.
 *
 * Las tres se evaluan DENTRO del contexto de clinica de la peticion, asi que
 * las consultas heredan la RLS. Ver docs/03.
 */
class EquipoDeCuidado
{
    /**
     * @param  Usuario  $usuario  debe ser profesional de la clinica.
     */
    public function incluye(Usuario $usuario, Paciente $paciente): bool
    {
        $profesionalId = $usuario->profesional_id;

        if ($profesionalId === null) {
            return false;
        }

        if ($usuario->tenant_id !== $paciente->tenant_id) {
            return false;
        }

        if ($this->tieneAsignacion($usuario->tenant_id, $paciente->id, $profesionalId)) {
            return true;
        }

        if ($this->tieneCita($usuario->tenant_id, $paciente->id, $profesionalId)) {
            return true;
        }

        return $this->tieneEvolucionPropia($usuario->tenant_id, $paciente->id, $profesionalId);
    }

    private function tieneAsignacion(string $clinicaId, string $pacienteId, string $profesionalId): bool
    {
        return AsignacionCuidado::query()
            ->where('tenant_id', $clinicaId)
            ->where('paciente_id', $pacienteId)
            ->where('profesional_id', $profesionalId)
            ->where('activo', true)
            ->exists();
    }

    private function tieneCita(string $clinicaId, string $pacienteId, string $profesionalId): bool
    {
        return Cita::query()
            ->where('tenant_id', $clinicaId)
            ->where('paciente_id', $pacienteId)
            ->where('profesional_id', $profesionalId)
            ->exists();
    }

    private function tieneEvolucionPropia(string $clinicaId, string $pacienteId, string $profesionalId): bool
    {
        return Evolucion::query()
            ->where('tenant_id', $clinicaId)
            ->where('paciente_id', $pacienteId)
            ->where('profesional_id', $profesionalId)
            ->exists();
    }
}