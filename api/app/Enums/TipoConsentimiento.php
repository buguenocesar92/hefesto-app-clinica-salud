<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de consentimiento que el paciente (o su representante) entrega a la
 * clinica.
 *
 * Un consentimiento NO se firma en esta aplicacion: se firma en DocuSeal y acá
 * se guarda solo la referencia del documento firmado
 * (`documento_referencia_externo`). Ver docs/03.
 */
enum TipoConsentimiento: string
{
    case Tratamiento = 'tratamiento';
    case Privacidad = 'privacidad';
    case UsoDeDatos = 'uso_de_datos';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Tratamiento => 'Consentimiento de tratamiento',
            self::Privacidad => 'Consentimiento de privacidad',
            self::UsoDeDatos => 'Consentimiento de uso de datos',
        };
    }
}