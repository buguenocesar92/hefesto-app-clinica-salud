<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Consentimiento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Consentimiento registrado.
 *
 * @mixin Consentimiento
 */
class ConsentimientoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $peticion): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo->value,
            'tipo_etiqueta' => $this->tipo->etiqueta(),
            'otorgado' => $this->otorgado,
            'fecha' => $this->fecha->toDateString(),
            'documento_referencia_externo' => $this->documento_referencia_externo,
            'firmado' => $this->firmado(),
            'notas' => $this->notas,
            'registrado_por' => $this->whenLoaded('registradoPor', fn () => $this->registradoPor?->nombre),
        ];
    }
}