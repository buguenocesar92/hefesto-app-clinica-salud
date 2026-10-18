<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Contexto de clinica (tenant) de la peticion.
 *
 * La RLS de PostgreSQL (migracion 2026_01_20_000016) lee la clinica desde la
 * variable de sesion `app.clinica_id`. Esta clase es la UNICA que la escribe.
 * Si alguien la fija por otro lado, el comportamiento deja de estar
 * garantizado: por eso `establecer()` es el unico punto de entrada y exige
 * estar dentro de una transaccion.
 *
 * MISMO PATRON DEL PORTAL DE AUTOSERVICIO
 * ---------------------------------------
 * Columna `tenant_id` mas Row Level Security, no un esquema por clinica. La
 * justificacion completa esta en
 * apps/portal-autoservicio/docs/03-MULTITENANCY.md y se repite, adaptada a
 * este dominio, en docs/03-MULTITENANCY-Y-ROLES.md. Aca el nombre de la
 * variable es `app.clinica_id` porque el tenant de esta aplicacion es una
 * clinica; el mecanismo es identico.
 *
 * ALCANCE DE TRANSACCION
 * ----------------------
 * Se usa `set_config(..., true)`: el valor desaparece al confirmar o revertir
 * la transaccion. Con un `SET` normal (ambito de sesion) el valor sobreviviria
 * a la transaccion y, en el worker de colas, que reutiliza la misma conexion
 * para trabajos de clinicas distintas, una tarea podria leer datos de otra
 * clinica. Ese es exactamente el fallo que este diseno evita.
 */
class ContextoTenant
{
    /** Variable de sesion de PostgreSQL que leen las politicas RLS. */
    public const VARIABLE = 'app.clinica_id';

    /**
     * Fija la clinica de la peticion en la conexion actual.
     *
     * @throws RuntimeException si no hay una transaccion abierta.
     */
    public function establecer(string $clinicaId): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException(
                'No se puede fijar el contexto de clinica fuera de una transaccion. '
                . 'Use App\Services\Tenancy\ContextoTenant::ejecutarAislado().'
            );
        }

        DB::select('SELECT set_config(?, ?, true)', [self::VARIABLE, $clinicaId]);
    }

    /**
     * Ejecuta una operacion dentro de una transaccion con la clinica fija.
     * Al salir, PostgreSQL descarta el valor de la variable.
     *
     * @template T
     *
     * @param  Closure(): T  $operacion
     * @return T
     */
    public function ejecutarAislado(string $clinicaId, Closure $operacion): mixed
    {
        return DB::transaction(function () use ($clinicaId, $operacion) {
            $this->establecer($clinicaId);

            return $operacion();
        });
    }

    /**
     * Lee la variable de sesion sin pasar por `public.app_clinica_id()`, que
     * lanza excepcion cuando no hay contexto.
     *
     * Sirve para diagnostico: si devuelve null, ninguna politica RLS de la
     * base va a devolver filas.
     */
    public function clinicaIdEnConexion(): ?string
    {
        $fila = DB::select('SELECT current_setting(?, true) AS valor', [self::VARIABLE]);

        if ($fila === [] || $fila[0]->valor === null || $fila[0]->valor === '') {
            return null;
        }

        return (string) $fila[0]->valor;
    }

    /**
     * Ejecuta una operacion sin contexto de clinica.
     *
     * Solo para tareas que por definicion cruzan clinicas: la creacion de una
     * clinica nueva, el comando `clinica:verificar-aislamiento` y la
     * comprobacion de salud. Cualquier consulta a una tabla protegida fallara
     * en RLS, que es el comportamiento correcto.
     *
     * @template T
     *
     * @param  Closure(): T  $operacion
     * @return T
     */
    public function ejecutarSinClinica(Closure $operacion): mixed
    {
        return $operacion();
    }
}