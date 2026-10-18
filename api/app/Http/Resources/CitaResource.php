<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Cita;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cita de la agenda.
 *
 * No incluye notas de la evolucion ni receta: la agenda no es informacion
 * clinica. Si lo fuera, dejaria de ser una capacidad de mostrador.
 *
 * @mixin Cita
 */
class CitaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $peticion): array
    {
        return [
            'id' => $this->id,
            'inicio' => $this->inicio->toIso8601String(),
            'fin' => $this->fin->toIso8601String(),
            'duracion_minutos' => $this->duracionMinutos(),
            'estado' => $this->estado->value,
            'estado_etiqueta' => $this->estado->etiqueta(),
            'motivo_consulta' => $this->motivo_consulta,
            'notas' => $this->notas,
            'paciente' => $this->whenLoaded('paciente', fn () => [
                'id' => $this->paciente->id,
                'nombre' => $this->paciente->nombreCompleto(),
                'telefono' => $this->paciente->telefono,
            ]),
            'profesional' => $this->whenLoaded('profesional', fn () => [
                'id' => $this->profesional->id,
                'nombre' => $this->profesional->nombre,
            ]),
            'sala' => $this->whenLoaded('sala', fn () => $this->sala === null ? null : [
                'id' => $this->sala->id,
                'nombre' => $this->sala->nombre,
            ]),
            'tipo_atencion' => $this->whenLoaded('tipoAtencion', fn () => $this->tipoAtencion === null ? null : [
                'id' => $this->tipoAtencion->id,
                'nombre' => $this->tipoAtencion->nombre,
            ]),
        ];
    }
}