<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Resultado de un intento de acceso a ficha clinica.
 *
 * Los intentos DENEGADOS tambien se guardan. Un acceso denegado es
 * informacion: es la forma de detectar que alguien esta probando el sistema
 * con el rol equivocado. Ver docs/07.
 */
enum ResultadoAcceso: string
{
    case Permitido = 'permitido';
    case Denegado = 'denegado';
}