<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Rol de la persona dentro de la clinica.
 *
 * El rol es la SEMILLA del permiso, no el permiso. Lo que decide de verdad si
 * una persona puede leer una ficha clinica es
 * App\Services\Permisos\CapacidadesDeUsuario, que ademas mira si el
 * profesional trata a ese paciente.
 *
 * El orden de los casos importa: `esProfesional()` recorre la lista y gana el
 * primero que cumple, asi que Administrador se prueba antes que Profesional.
 */
enum RolClinico: string
{
    case Recepcion = 'recepcion';
    case Profesional = 'profesional';
    case Administrador = 'administrador';

    /**
     * Etiqueta para mostrar en la interfaz.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Recepcion => 'Recepción',
            self::Profesional => 'Profesional',
            self::Administrador => 'Administración',
        };
    }

    /**
     * El nombre del equipo en Authentik que otorga este rol.
     *
     * Sirve para la guia de alta de cuentas (docs/06). La comparacion es
     * insensible a mayusculas porque Authentik entrega los equipos con el
     * nombre tal como se escribio en la consola.
     */
    public function equipoAuthentik(): string
    {
        return match ($this) {
            self::Recepcion => 'Recepcion de ClinicaSalud',
            self::Profesional => 'Profesionales de ClinicaSalud',
            self::Administrador => 'Administradores de ClinicaSalud',
        };
    }
}