<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Usuario;

use App\Models\Profesional;

/**
 * Administracion de profesionales de la clinica.
 */
class ProfesionalPolicy
{
    use Concerns\PolicyDeAdministracion;

    /**
     * La lista de profesionales la necesita TODA la agenda, no solo la
     * administracion: al agendar hay que elegir a quien se agenda.
     *
     * Ojo: este permiso abre el NOMBRE y la ESPECIALIDAD del profesional.
     * No abre su ficha clinica, que no existe como recurso separado: su
     * informacion de salud es la de los pacientes que atiende, y esa se
     * protege en PacientePolicy::verFicha.
     */
    public function verParaAgenda(Usuario $usuario): bool
    {
        return $usuario->puedeAgendar();
    }
}