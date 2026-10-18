<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una cita en la agenda.
 *
 * `Atendida` y `NoAsistio` son los dos finales reales de una reserva. El estado
 * importa para mas que la agenda: una cita atendida es una de las formas en
 * que un profesional queda como tratante de ese paciente y por lo tanto puede
 * ver su ficha clinica. Ver docs/03.
 */
enum EstadoCita: string
{
    case Agendada = 'agendada';
    case Confirmada = 'confirmada';
    case Atendida = 'atendida';
    case Cancelada = 'cancelada';
    case NoAsistio = 'no_asistio';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Agendada => 'Agendada',
            self::Confirmada => 'Confirmada',
            self::Atendida => 'Atendida',
            self::Cancelada => 'Cancelada',
            self::NoAsistio => 'No asistió',
        };
    }

    /**
     * Estados en los que la cita sigue reservando espacio en la agenda.
     *
     * Cancelada y NoAsistio liberan el bloque. Por eso las consultas de
     * disponibilidad filtran por estos estados y no por todos.
     */
    public function ocupaAgenda(): bool
    {
        return match ($this) {
            self::Agendada, self::Confirmada, self::Atendida => true,
            self::Cancelada, self::NoAsistio => false,
        };
    }

    /**
     * El estado ya no se puede cambiar.
     */
    public function esFinal(): bool
    {
        return match ($this) {
            self::Atendida, self::Cancelada, self::NoAsistio => true,
            self::Agendada, self::Confirmada => false,
        };
    }
}