<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Paciente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha de IDENTIFICACION del paciente: lo que recepcion puede ver.
 *
 * No incluye ni una evolucion ni una receta, y no las referencia. La historia
 * clinica se entrega en otra ruta (`fichas/{paciente}`), que exige ser
 * profesional tratante. Ver docs/03.
 *
 * @mixin Paciente
 */
class PacienteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $peticion): array
    {
        return [
            'id' => $this->id,
            'rut' => $this->rut,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'nombre_completo' => $this->nombreCompleto(),
            'fecha_nacimiento' => $this->fecha_nacimiento?->toDateString(),
            'edad' => $this->edad(),
            'sexo' => $this->sexo?->value,
            'telefono' => $this->telefono,
            'correo' => $this->correo,
            'direccion' => $this->direccion,
            'cobertura' => $this->cobertura,
            'activo' => $this->activo,
            'tiene_consentimientos' => $this->whenLoaded('consentimientos', fn () => $this->consentimientos->count() > 0),
        ];
    }
}