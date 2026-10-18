<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Zona horaria
    |--------------------------------------------------------------------------
    |
    | Chile continental: America/Santiago (UTC-3 en horario de verano, UTC-4
    | en horario de invierno). Si la clinica esta en isla de Pascua o en
    | Magallanes, la zona va en la fila `clinicas` de esa clinica y la
    | aplicacion la usa al presentar fechas. Ver docs/05.
    |
    */

    'zona_horaria' => env('APP_TIMEZONE', 'America/Santiago'),

    'locale' => 'es_CL',

    /*
    |--------------------------------------------------------------------------
    | Agenda
    |--------------------------------------------------------------------------
    */

    'duracion_maxima_minutos' => (int) env('CLINICA_DURACION_MAXIMA_MINUTOS', 240),

    'anticipacion_maxima_dias' => (int) env('CLINICA_ANTICIPACION_MAXIMA_DIAS', 180),

    'margen_entre_citas_minutos' => (int) env('CLINICA_MARGEN_ENTRE_CITAS', 0),

    /*
    |--------------------------------------------------------------------------
    | Ficha clinica
    |--------------------------------------------------------------------------
    */

    /*
    | Cuanto tiempo se conserva la bitacora de accesos. No es un tecnicismo:
    | es el plazo que la clinica podria tener que demostrar ante una
    | denuncia o un reclamo. Ver docs/07.
    */
    'retencion_bitacora_dias' => (int) env('CLINICA_RETENCION_BITACORA_DIAS', 3650),

    /*
    | Alertas de la bitacora. Son heuristicas de revision, no deteccion de
    | incidentes: un volumen alto de intentos denegados suele significar que
    | alguien esta probando el sistema o que una persona esta trabajando con
    | el rol equivocado. Ver docs/07.
    */
    'alerta_denegados_por_hora' => (int) env('CLINICA_ALERTA_DENEGADOS_POR_HORA', 20),

    'alerta_denegados_por_usuario' => (int) env('CLINICA_ALERTA_DENEGADOS_POR_USUARIO', 5),

    /*
    |--------------------------------------------------------------------------
    | Firma de documentos
    |--------------------------------------------------------------------------
    |
    | ClinicaSalud NO firma recetas de forma criptografica. La referencia al
    | documento firmado llega desde DocuSeal, que ya esta en el catalogo de
    | Hefesto. Aqui solo se guarda el identificador de esa referencia.
    |
    */
    'proveedor_firma' => 'docuseal',

];