<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos para emitir una receta.
 *
 * `documento_referencia_externo` es la referencia del documento firmado en
 * DocuSeal. ClinicaSalud no firma: si viene nulo, la receta queda emitida pero
 * sin firma, y la interfaz avisa. Ver docs/03.
 */
class EmitirRecetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cita_id' => ['nullable', 'uuid'],
            'fecha' => ['nullable', 'date'],
            'contenido' => ['required', 'string', 'max', '8000'],
            'emitir' => ['sometimes', 'boolean'],
            'documento_referencia_externo' => ['nullable', 'string', 'max', '120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contenido.required' => 'La receta necesita su contenido.',
            'contenido.max' => 'La receta es demasiado larga. Divida el contenido si es necesario.',
        ];
    }
}