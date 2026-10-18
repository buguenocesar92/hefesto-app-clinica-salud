<?php

declare(strict_types=1);

namespace App\Http\Controllers\FichaClinica\Concerns;

use App\Models\Paciente;
use Illuminate\Http\Request;

/**
 * Resolucion del paciente en las rutas de ficha clinica.
 *
 * POR QUE SE RESUELVE DENTRO DEL CONTROLADOR Y NO CON `{paciente}` TIPADO
 * ----------------------------------------------------------------------
 * Si la ruta declarara `Route::get('fichas/{paciente}')` con el controlador
 * esperando un modelo, Laravel haria la sustitucion de parametros ANTES de
 * ejecutar el middleware `ficha.auditada`. Entonces, pedir la ficha de un
 * paciente inexistente (o de otra clinica, invisible por RLS) lanzaria la
 * excepcion antes de auditarse, y el intento no quedaria registrado.
 *
 * Resolviendo aqui, el orden es: auditar -> buscar (RLS) -> Policy -> 404 o
 * contenido. El intento siempre queda anotado.
 */
trait ResuelvePacienteDeFicha
{
    protected function pacienteDeFicha(Request $peticion, string $pacienteId): Paciente
    {
        $modelo = Paciente::query()
            ->where('tenant_id', $peticion->user()->tenant_id)
            ->whereKey($pacienteId)
            ->first();

        if ($modelo === null) {
            // 404 y no 403: no se confirma que el paciente exista en otra
            // clinica. En datos de salud, responder "existe pero no puedes"
            // ya es filtrar informacion.
            abort(404, 'No encontrado.');
        }

        return $modelo;
    }
}