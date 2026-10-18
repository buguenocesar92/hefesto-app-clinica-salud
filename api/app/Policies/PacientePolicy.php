<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Paciente;
use App\Models\Usuario;

/**
 * Permisos sobre la ficha de identificacion del paciente (datos personales:
 * nombre, RUT, contacto, cobertura).
 *
 * OJO CON EL NOMBRE: aqui esta la ficha de IDENTIFICACION, no la ficha
 * CLINICA. Los tres roles pueden verla: recepcion necesita saber a quien
 * agenda. Lo que no pueden ver son las evoluciones, que viven en otra Policy
 * (EvolucionPolicy). La confusion entre estas dos fichas es justamente el
 * error que este producto evita. Ver docs/03.
 */
class PacientePolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->puedeAgendar();
    }

    public function view(Usuario $usuario, Paciente $paciente): bool
    {
        // La RLS ya garantiza que `paciente` es de esta clinica. Esta comprobacion
        // es la segunda linea, para el caso de que alguien llame a la Policy a
        // mano fuera del contexto de la peticion.
        return $usuario->puedeAgendar() && $usuario->tenant_id === $paciente->tenant_id;
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->puedeAgendar();
    }

    public function update(Usuario $usuario, Paciente $paciente): bool
    {
        return $this->view($usuario, $paciente);
    }

    /**
     * Ver la HISTORIA CLINICA del paciente: evoluciones y recetas.
     *
     * Esta es la comprobacion que separa el producto de un sistema de reservas
     * con un campo de notas. Recibe de vuelta el paciente entero para que la
     * Policy pueda evaluar el equipo de cuidado.
     */
    public function verFicha(Usuario $usuario, Paciente $paciente): bool
    {
        return $usuario->puedeLeerFicha($paciente);
    }
}