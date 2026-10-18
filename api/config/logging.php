<?php

declare(strict_types=1);

use Monolog\Handler\StreamHandler;

return [

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Depuracion
    |--------------------------------------------------------------------------
    |
    | Con `APP_DEBUG` en falso, el registro de la peticion NO guarda el cuerpo:
    | en una clinica el cuerpo de la peticion puede ser la evolucion de un
    | paciente. El nivel `info` mas abajo ya guarda ruta, metodo y duracion,
    | que es lo justo para diagnosticar sin copiar informacion clinica al log.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single,stderr')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/clinica.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'replace_placeholders' => true,
        ],

        // Salida a consola: es la que leen los contenedores.
        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'info'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [Monolog\Processor\PsrLogMessageProcessor::class],
        ],

        /*
        | Bitacora de intentos de acceso a ficha clinica que no llegaron a la
        | base de datos. Ocurre cuando la peticion ni siquiera tiene clinica
        | (no hay sesion, o el usuario no tiene clinica asociada): en ese
        | momento no hay tenant con el que escribir, asi que el intento queda
        | aca. Ver App\Http\Middleware\AuditarFicha.
        */
        'accesos_rechazados' => [
            'driver' => 'single',
            'path' => storage_path('logs/accesos-rechazados.log'),
            'level' => 'warning',
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/clinica.log'),
        ],

    ],

];