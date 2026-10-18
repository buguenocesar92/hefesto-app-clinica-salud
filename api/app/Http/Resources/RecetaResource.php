<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Receta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Receta o certificado.
 *
 * `documento_referencia_externo` apunta al documento firmado en DocuSeal. Si es
 * nulo, la receta esta emitida pero sin firma: la interfaz lo dice en
 * pantalla, para que nadie la entregue al paciente creyendo que esta firmada.
 *
 * @mixin Receta
 */
class RecetaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $peticion): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha->toIso8601String(),
            'contenido' => $this->contenido,
            'estado' => $this->estado->value,
            'estado_etiqueta' => $this->estado->etiqueta(),
            'emitida_en' => $this->emitida_en?->toIso8601String(),
            'documento_referencia_externo' => $this->documento_referencia_externo,
            'firmada' => $this->firmada(),
            'profesional' => $this->whenLoaded('profesional', fn () => [
                'id' => $this->profesional->id,
                'nombre' => $this->profesional->nombre,
                'profesion' => $this->profesional->profesion,
                'registro_profesional' => $this->profesional->registro_profesional,
            ]),
        ];
    }
}