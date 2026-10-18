<?php

declare(strict_types=1);

namespace App\Http\Controllers\FichaClinica;

use App\Http\Controllers\FichaClinica\Concerns\ResuelvePacienteDeFicha;
use App\Http\Controllers\Controller;
use App\Http\Resources\EvolucionResource;
use App\Http\Resources\RecetaResource;
use App\Models\AsignacionCuidado;
use App\Models\Consentimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La ficha clinica: todo el historial de un paciente, en una vista.
 *
 * Una sola ruta abre toda la historia. Quien llega aca ya paso por:
 *   - `auth`: tiene sesion.
 *   - `ResolverTenant`: hay una transaccion con la clinica fijada, y la RLS
 *     ya no deja ver filas de otra clinica.
 *   - `ficha.auditada`: este acceso quedo anotado en la bitacora, permitido o
 *     no.
 *   - `PacientePolicy::verFicha`: es profesional TRATANTE de este paciente.
 *
 * Que recepcion reciba 404 en esta ruta es el comportamiento esperado, no un
 * error. Ver tests/Feature/Permisos/RecepcionNoLeeFichaClinicaTest.
 */
class HistorialController extends Controller
{
    use ResuelvePacienteDeFicha;

    public function show(Request $peticion, string $paciente): JsonResponse
    {
        $modelo = $this->pacienteDeFicha($peticion, $paciente);

        $this->authorize('verFicha', $modelo);

        $evoluciones = $modelo->evoluciones()
            ->with('profesional:id,tenant_id,nombre,profesion,registro_profesional')
            ->orderByDesc('fecha')
            ->get();

        $recetas = $modelo->recetas()
            ->with('profesional:id,tenant_id,nombre,profesion,registro_profesional')
            ->orderByDesc('fecha')
            ->get();

        return response()->json([
            'paciente' => [
                'id' => $modelo->id,
                'nombre' => $modelo->nombreCompleto(),
                'rut' => $modelo->rut,
                'fecha_nacimiento' => $modelo->fecha_nacimiento?->toDateString(),
                'edad' => $modelo->edad(),
                'cobertura' => $modelo->cobertura,
            ],
            'consentimientos' => Consentimiento::query()
                ->where('tenant_id', $modelo->tenant_id)
                ->where('paciente_id', $modelo->id)
                ->orderByDesc('fecha')
                ->get(['id', 'tipo', 'otorgado', 'fecha', 'documento_referencia_externo']),
            'equipo_de_cuidado' => AsignacionCuidado::query()
                ->where('tenant_id', $modelo->tenant_id)
                ->where('paciente_id', $modelo->id)
                ->where('activo', true)
                ->with('profesional:id,tenant_id,nombre,profesion')
                ->get(['id', 'profesional_id']),
            'evoluciones' => EvolucionResource::collection($evoluciones)->resolve($peticion),
            'recetas' => RecetaResource::collection($recetas)->resolve($peticion),
        ]);
    }
}