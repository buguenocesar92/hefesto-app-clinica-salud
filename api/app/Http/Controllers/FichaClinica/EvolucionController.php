<?php

declare(strict_types=1);

namespace App\Http\Controllers\FichaClinica;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FichaClinica\Concerns\ResuelvePacienteDeFicha;
use App\Http\Requests\RegistrarEvolucionRequest;
use App\Http\Resources\EvolucionResource;
use App\Models\Evolucion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Evoluciones de un paciente: la historia clinica escrita.
 *
 * Solo el profesional tratante. La auditoria la hace el middleware de la ruta
 * (ver AuditarFicha), no este controlador: asi queda registrado tambien el
 * intento que se rechaza.
 */
class EvolucionController extends Controller
{
    use ResuelvePacienteDeFicha;

    public function index(Request $peticion, string $paciente)
    {
        $modelo = $this->pacienteDeFicha($peticion, $paciente);

        $this->authorize('verFicha', $modelo);

        $evoluciones = $modelo->evoluciones()
            ->with('profesional:id,tenant_id,nombre,profesion,registro_profesional')
            ->orderByDesc('fecha')
            ->paginate(25);

        return EvolucionResource::collection($evoluciones);
    }

    public function show(Request $peticion, string $paciente, string $evolucion)
    {
        $modelo = $this->pacienteDeFicha($peticion, $paciente);

        $registro = Evolucion::query()
            ->where('tenant_id', $modelo->tenant_id)
            ->where('paciente_id', $modelo->id)
            ->whereKey($evolucion)
            ->with('profesional')
            ->first();

        if ($registro === null) {
            abort(404, 'No encontrado.');
        }

        $this->authorize('view', $registro);

        return new EvolucionResource($registro);
    }

    public function store(RegistrarEvolucionRequest $peticion, string $paciente): JsonResponse
    {
        $modelo = $this->pacienteDeFicha($peticion, $paciente);

        $this->authorize('create', [Evolucion::class, $modelo]);

        $datos = $peticion->validated();
        $usuario = $peticion->user();

        $evolucion = Evolucion::query()->create([
            'tenant_id' => $modelo->tenant_id,
            'paciente_id' => $modelo->id,
            'profesional_id' => $usuario->profesional_id,
            'cita_id' => $datos['cita_id'] ?? null,
            'fecha' => $datos['fecha'] ?? now(),
            'motivo_consulta' => $datos['motivo_consulta'],
            'codigo_cie10' => $datos['codigo_cie10'] ?? null,
            'diagnostico' => $datos['diagnostico'],
            'tratamiento' => $datos['tratamiento'],
            'indicaciones' => $datos['indicaciones'] ?? null,
            'observaciones' => $datos['observaciones'] ?? null,
        ]);

        $evolucion->load('profesional');

        return (new EvolucionResource($evolucion))->response()->setStatusCode(201);
    }
}