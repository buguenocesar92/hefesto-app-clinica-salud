<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sexo registrado en la ficha de identificacion del paciente.
 *
 * Es dato de identificacion, NO dato clinico: por eso recepcion lo ve. El
 * formulario es abierto porque Chile no exige un catalogo cerrado; `NoInformado`
 * existe para que la ficha quede completa sin obligar a decidir. Ver docs/07.
 */
enum Sexo: string
{
    case Femenino = 'F';
    case Masculino = 'M';
    case Otro = 'O';
    case NoInformado = 'N';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Femenino => 'Femenino',
            self::Masculino => 'Masculino',
            self::Otro => 'Otro',
            self::NoInformado => 'No informado',
        };
    }
}