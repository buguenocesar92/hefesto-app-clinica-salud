<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una receta.
 *
 * La receta nace en Borrador y pasa a Emitida cuando el profesional la entrega.
 * Anular una receta NO la borra: queda con estado Anulada y con el profesional
 * que la emitio. En salud, borrar un registro no es una operacion que se pueda
 * deshacer. Ver docs/07.
 */
enum EstadoReceta: string
{
    case Borrador = 'borrador';
    case Emitida = 'emitida';
    case Anulada = 'anulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Emitida => 'Emitida',
            self::Anulada => 'Anulada',
        };
    }
}