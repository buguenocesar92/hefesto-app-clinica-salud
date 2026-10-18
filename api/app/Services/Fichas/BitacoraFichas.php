<?php

declare(strict_types=1);

namespace App\Services\Fichas;

use App\Enums\AccionFicha;
use App\Enums\ResultadoAcceso;
use App\Models\AccesoFicha;
use App\Models\Usuario;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bitacora de accesos a la ficha clinica.
 *
 * Sin excepciones: TODO intento de abrir informacion clinica queda anotado,
 * tanto el permitido como el denegado.
 *
 * POR QUE UNA CONEXION APARTE
 * --------------------------
 * La peticion corre dentro de una transaccion (ver ResolverTenant). Si el
 * acceso es denegado, el controlador lanza una excepcion y PostgreSQL REVIERTE
 * esa transaccion: una bitacora escrita en la misma conexion se perderia justo
 * en el caso que mas importa. Por eso esta clase escribe en la conexion
 * `auditoria`, separada de la de la peticion y con su propia transaccion. El
 * costo es una conexion mas; la alternativa es perder el registro de los
 * intentos denegados.
 *
 * Esa conexion se puede apuntar a otra base de datos (incluso a otra maquina)
 * sin tocar el codigo, si la clinica lo necesita. Ver docs/07.
 *
 * SI LA PETICION NO TIENE CLINICA
 * ------------------------------
 * Una peticion sin sesion, o de alguien sin clinica asociada, no tiene con que
 * escribir: no hay variable de tenant. En ese caso el intento se manda al
 * registro de `accesos-rechazados` (ver config/logging.php). Es una defensa
 * secundaria: esos casos tambien los corta `auth`, y quedan en el registro
 * general del servidor.
 */
class BitacoraFichas
{
    /** Conexion dedicada a la bitacora. Ver la nota de clase. */
    public const CONEXION = 'auditoria';

    public function __construct(private readonly ContextoTenant $contexto) {}

    /**
     * Anota un intento de acceso a ficha clinica.
     *
     * @param  Usuario|null  $usuario  null si la peticion no tiene sesion.
     * @param  string|null  $pacienteId  id del paciente, si se pudo resolver
     *                                   desde la ruta.
     * @param  ResultadoAcceso  $resultado
     */
    public function registrar(
        ?Usuario $usuario,
        ?string $pacienteId,
        AccionFicha $accion,
        ResultadoAcceso $resultado,
        Request $peticion,
        ?int $codigoRespuesta = null,
    ): void {
        $clinicaId = $usuario?->tenant_id;

        $campos = [
            'usuario_id' => $usuario?->id,
            'paciente_id' => $pacienteId,
            'accion' => $accion->value,
            'resultado' => $resultado->value,
            'metodo_http' => $peticion->method(),
            'ruta' => $peticion->path(),
            'nombre_ruta' => $peticion->route()?->getName(),
            'direccion_ip' => $peticion->ip(),
            'agente_usuario' => substr((string) $peticion->userAgent(), 0, 500),
            'codigo_respuesta' => $codigoRespuesta,
        ];

        if ($clinicaId === null) {
            // No hay contexto de clinica: no se puede escribir en la base
            // (la RLS lo prohibe) y no se debe inventar una clinica.
            Log::channel('accesos_rechazados')->warning('Intento de acceso a ficha clinica sin clinica en la sesion.', [
                'usuario_id' => $usuario?->id,
                'correo' => $usuario?->correo,
                'accion' => $accion->value,
                'resultado' => $resultado->value,
                'ruta' => $peticion->path(),
                'metodo' => $peticion->method(),
                'direccion_ip' => $peticion->ip(),
            ]);

            return;
        }

        try {
            DB::connection(self::CONEXION)->transaction(function () use ($clinicaId, $campos): void {
                DB::connection(self::CONEXION)->select(
                    'SELECT set_config(?, ?, true)',
                    [ContextoTenant::VARIABLE, $clinicaId]
                );

                AccesoFicha::on(self::CONEXION)->create(array_merge($campos, [
                    'tenant_id' => $clinicaId,
                ]));
            });
        } catch (Throwable $e) {
            // Si la bitacora falla, la peticion NO debe continuar: una ficha
            // clinica que se puede leer sin dejar rastro es peor que una
            // peticion que falla. Se corta el acceso y se avisa al operador.
            Log::channel('accesos_rechazados')->critical('No se pudo escribir la bitacora de accesos a ficha clinica. Se corta la peticion.', [
                'clinica_id' => $clinicaId,
                'usuario_id' => $campos['usuario_id'],
                'accion' => $accion->value,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}