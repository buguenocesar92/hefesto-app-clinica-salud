<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Evolucion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Evolucion de la historia clinica.
 *
 * Solo se entrega por rutas bajo `fichas/`, que exigen ser profesional
 * tratante y quedan registradas en la bitacora. Este Resource no es una
 * defensa: solo decide que forma tiene la respuesta.
 *
 * @mixin Evolución
 */
class EvolucionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $peticion): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha->toIso8601String(),
            'motivo_consulta' => $this->motivo_consulta,
            'codigo_cie10' => $this->codigo_cie10,
            'diagnostico' => $this->diagnostico,
            'tratamiento' => $this->tratamiento,
            'indicaciones' => $this->indicaciones,
            'observaciones' => $this->observaciones,
            'profesional' => $this->whenLoaded('profesional', fn () => [
                'id' => $this->profesional->id,
                'nombre' => $this->profesional->nombre,
                'profesion' => $this->profesional->profesion,
                'registro_profesional' => $this->profesional->registro_profesional,
            ]),
            'cita_id' => $this->cita_id,
        ];
    }
}