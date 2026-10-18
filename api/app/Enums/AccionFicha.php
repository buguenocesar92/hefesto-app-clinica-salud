<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Accion que se registro en la bitacora de accesos a la ficha clinica.
 *
 * Se guardan como texto (`accion`) y no como tipo de base: anadir una accion
 * nueva no puede exigir una migracion en un sistema que ya tiene fichas de
 * pacientes. El enum documenta el catalogo vigente y la interfaz lo usa.
 */
enum AccionFicha: string
{
    case VerHistorial = 'ver_historial';
    case VerEvolucion = 'ver_evolucion';
    case CrearEvolucion = 'crear_evolucion';
    case VerReceta = 'ver_receta';
    case CrearReceta = 'crear_receta';
    case AnularReceta = 'anular_receta';
    case ImprimirReceta = 'imprimir_receta';

    public function etiqueta(): string
    {
        return match ($this) {
            self::VerHistorial => 'Consultó la ficha',
            self::VerEvolucion => 'Abrió una evolución',
            self::CrearEvolucion => 'Registró una evolución',
            self::VerReceta => 'Abrió una receta',
            self::CrearReceta => 'Emitió una receta',
            self::AnularReceta => 'Anuló una receta',
            self::ImprimirReceta => 'Imprimió una receta',
        };
    }

    /**
     * Si la accion deja ver informacion clinica que ya existia.
     *
     * No cambia la obligacion de auditar: TODO acceso se anota. Sirve para
     * distinguir en la interfaz entre "miro" y "escribio".
     */
    public function esLectura(): bool
    {
        return match ($this) {
            self::VerHistorial, self::VerEvolucion, self::VerReceta, self::ImprimirReceta => true,
            self::CrearEvolucion, self::CrearReceta, self::AnularReceta => false,
        };
    }
}