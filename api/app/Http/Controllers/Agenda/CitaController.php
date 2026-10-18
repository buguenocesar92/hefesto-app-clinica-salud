<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agenda;

use App\Enums\EstadoCita;
use App\Http\Controllers\Controller;
use App\Http\Requests\AgendarCitaRequest;
use App\Http\Resources\CitaResource;
use App\Models\Cita;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\TipoAtencion;
use App\Services\Agenda\VerificadorDeDisponibilidad;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Alta, edicion y cancelacion de citas.
 *
 * El fin NUNCA viene del cliente: se calcula con la duracion del tipo de
 * atencion. Ver AgendarCitaRequest.
 *
 * `DELETE` significa cancelar, no borrar. Ver CitaPolicy::delete.
 */
class CitaController extends Controller
{
    public function __construct(private readonly VerificadorDeDisponibilidad $disponibilidad) {}

    public function index(Request $peticion)
    {
        $this->authorize('viewAny', Cita::class);

        $citas = Cita::query()
            ->with(['paciente:id,tenant_id,nombres,apellidos,telefono', 'profesional:id,tenant_id,nombre', 'sala:id,tenant_id,nombre'])
            ->when($peticion->filled('paciente_id'), fn ($consulta) => $consulta->where('paciente_id', $peticion->query('paciente_id')))
            ->when($peticion->filled('estado'), fn ($consulta) => $consulta->where('estado', $peticion->query('estado')))
            ->orderByDesc('inicio')
            ->limit(200)
            ->get();

        return CitaResource::collection($citas);
    }

    public function show(Cita $cita)
    {
        $this->authorize('view', $cita);

        $cita->load(['paciente', 'profesional', 'sala', 'tipoAtencion']);

        return new CitaResource($cita);
    }

    public function store(AgendarCitaRequest $peticion): JsonResponse
    {
        $this->authorize('create', Cita::class);

        $datos = $peticion->validated();
        $clinicaId = (string) $peticion->user()->tenant_id;

        $inicio = Carbon::parse($datos['inicio']);

        $paciente = Paciente::query()->where('tenant_id', $clinicaId)->whereKey($datos['paciente_id'])->where('activo', true)->first();

        if ($paciente === null) {
            return $this->error('El paciente no existe o esta inactivo en esta clinica.');
        }

        $profesional = $this->disponibilidad->profesionalActivo($clinicaId, $datos['profesional_id']);

        if ($profesional === null) {
            return $this->error('El profesional no existe o esta inactivo en esta clinica.');
        }

        $salaId = $datos['sala_id'] ?? null;

        if ($salaId !== null && $this->disponibilidad->salaActiva($clinicaId, $salaId) === null) {
            return $this->error('La sala no existe o esta inactiva en esta clinica.');
        }

        $duracion = $this->duracionDe($clinicaId, $datos['tipo_atencion_id'] ?? null);
        $fin = $this->disponibilidad->calcularFin($inicio, $duracion);

        if (! $this->disponibilidad->profesionalEstaLibre($clinicaId, $profesional->id, $inicio, $fin)) {
            return $this->error('El profesional no esta libre en ese horario.');
        }

        if ($salaId !== null && ! $this->disponibilidad->salaEstaLibre($clinicaId, $salaId, $inicio, $fin)) {
            return $this->error('La sala no esta libre en ese horario.');
        }

        $cita = Cita::query()->create([
            'tenant_id' => $clinicaId,
            'paciente_id' => $paciente->id,
            'profesional_id' => $profesional->id,
            'sala_id' => $salaId,
            'tipo_atencion_id' => $datos['tipo_atencion_id'] ?? null,
            'inicio' => $inicio,
            'fin' => $fin,
            'estado' => $datos['estado'] ?? EstadoCita::Agendada,
            'motivo_consulta' => $datos['motivo_consulta'] ?? null,
            'notas' => $datos['notas'] ?? null,
            'creado_por_usuario_id' => $peticion->user()->id,
        ]);

        $cita->load(['paciente', 'profesional', 'sala', 'tipoAtencion']);

        return (new CitaResource($cita))->response()->setStatusCode(201);
    }

    public function update(Request $peticion, Cita $cita): JsonResponse
    {
        $this->authorize('update', $cita);

        $datos = $peticion->validate([
            'inicio' => ['sometimes', 'date'],
            'sala_id' => ['sometimes', 'nullable', 'uuid'],
            'tipo_atencion_id' => ['sometimes', 'nullable', 'uuid'],
            'estado' => ['sometimes'],
            'motivo_consulta' => ['sometimes', 'nullable', 'string', 'max', '240'],
            'notas' => ['sometimes', 'nullable', 'string', 'max', '500'],
            'motivo_cancelacion' => ['sometimes', 'nullable', 'string', 'max', '240'],
        ]);

        $clinicaId = (string) $peticion->user()->tenant_id;
        $inicio = isset($datos['inicio']) ? Carbon::parse($datos['inicio']) : $cita->inicio->copy();

        $duracion = $this->duracionDe($clinicaId, $datos['tipo_atencion_id'] ?? $cita->tipo_atencion_id);
        $fin = $this->disponibilidad->calcularFin($inicio, $duracion);

        $salaId = array_key_exists('sala_id', $datos) ? $datos['sala_id'] : $cita->sala_id;

        if (! $this->disponibilidad->profesionalEstaLibre($clinicaId, $cita->profesional_id, $inicio, $fin, $cita->id)) {
            return $this->error('El profesional no esta libre en ese horario.');
        }

        if ($salaId !== null && ! $this->disponibilidad->salaEstaLibre($clinicaId, $salaId, $inicio, $fin, $cita->id)) {
            return $this->error('La sala no esta libre en ese horario.');
        }

        $cita->fill([
            'inicio' => $inicio,
            'fin' => $fin,
            'sala_id' => $salaId,
            'motivo_consulta' => $datos['motivo_consulta'] ?? $cita->motivo_consulta,
            'notas' => $datos['notas'] ?? $cita->notas,
        ]);

        if (array_key_exists('estado', $datos)) {
            $estado = EstadoCita::tryFrom((string) $datos['estado']);

            if ($estado === null) {
                return $this->error('Estado de cita desconocido.');
            }

            $cita->estado = $estado;
            $cita->motivo_cancelacion = $estado === EstadoCita::Cancelada
                ? ($datos['motivo_cancelacion'] ?? 'Sin motivo informado')
                : null;
        }

        $cita->save();
        $cita->load(['paciente', 'profesional', 'sala', 'tipoAtencion']);

        return (new CitaResource($cita))->response();
    }

    /**
     * Cancelar. No borrar.
     */
    public function destroy(Request $peticion, Cita $cita): JsonResponse
    {
        $this->authorize('delete', $cita);

        $motivo = $peticion->input('motivo_cancelacion');

        $cita->estado = EstadoCita::Cancelada;
        $cita->motivo_cancelacion = is_string($motivo) && $motivo !== ''
            ? mb_substr($motivo, 0, 240)
            : 'Cancelada sin motivo informado';

        $cita->save();

        return response()->json([
            'mensaje' => 'Cita cancelada.',
            'id' => $cita->id,
            'estado' => $cita->estado->value,
        ]);
    }

    /**
     * Duracion del tipo de atencion. Sin tipo, se usa la duracion por
     * defecto de la configuracion (30 minutos) y queda anotado en la
     * documentacion.
     */
    private function duracionDe(string $clinicaId, ?string $tipoAtencionId): int
    {
        if ($tipoAtencionId === null) {
            return 30;
        }

        $tipo = TipoAtencion::query()
            ->where('tenant_id', $clinicaId)
            ->whereKey($tipoAtencionId)
            ->first();

        if ($tipo === null) {
            abort(422, 'El tipo de atencion no existe en esta clinica.');
        }

        return $tipo->duracion_minutos;
    }

    private function error(string $mensaje): JsonResponse
    {
        return response()->json(['mensaje' => $mensaje], 422);
    }
}