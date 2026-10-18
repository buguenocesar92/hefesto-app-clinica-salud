<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pacientes;

use App\Enums\TipoConsentimiento;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConsentimientoResource;
use App\Models\Consentimiento;
use App\Models\Paciente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Consentimientos del paciente.
 *
 * Se registran en recepcion: es informacion de identificacion, no clinica.
 *
 * El documento firmado vive en DocuSeal. Acá se guarda la referencia
 * (`documento_referencia_externo`); la firma no se implementa acá, ni debe
 * implementarse. Ver docs/03.
 */
class ConsentimientoController extends Controller
{
    public function index(Paciente $paciente)
    {
        $this->authorize('view', $paciente);

        $consentimientos = Consentimiento::query()
            ->where('tenant_id', $paciente->tenant_id)
            ->where('paciente_id', $paciente->id)
            ->orderByDesc('fecha')
            ->get();

        return ConsentimientoResource::collection($consentimientos);
    }

    public function store(Request $peticion, Paciente $paciente): JsonResponse
    {
        $this->authorize('update', $paciente);

        $datos = $peticion->validate([
            'tipo' => ['required', Rule::enum(TipoConsentimiento::class)],
            'otorgado' => ['required', 'boolean'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'documento_referencia_externo' => ['nullable', 'string', 'max', '120'],
            'notas' => ['nullable', 'string', 'max', '500'],
        ]);

        $consentimiento = Consentimiento::query()->create([
            'tenant_id' => $paciente->tenant_id,
            'paciente_id' => $paciente->id,
            'tipo' => $datos['tipo'],
            'otorgado' => $datos['otorgado'],
            'fecha' => $datos['fecha'],
            'documento_referencia_externo' => $datos['documento_referencia_externo'] ?? null,
            'registrado_por_usuario_id' => $peticion->user()->id,
            'notas' => $datos['notas'] ?? null,
        ]);

        return (new ConsentimientoResource($consentimiento))->response()->setStatusCode(201);
    }
}