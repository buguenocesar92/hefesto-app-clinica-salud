<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos para registrar una evolucion.
 *
 * `codigo_cie10` se valida SOLO como texto corto. La aplicacion no trae el
 * catalogo CIE-10 del Ministerio de Salud, asi que no puede comprobar que el
 * codigo exista. Validarlo contra la fuente oficial es trabajo pendiente, y
 * esta declarado como tal en docs/10. Poner una validacion inventada seria
 * peor que ninguna: dejaria pasar datos malos con apariencia de buenos.
 */
class RegistrarEvolucionRequest extends FormRequest
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
            'motivo_consulta' => ['required', 'string', 'max', '240'],
            'codigo_cie10' => ['nullable', 'string', 'max', '16'],
            'diagnostico' => ['required', 'string', 'max', '2000'],
            'tratamiento' => ['required', 'string', 'max', '4000'],
            'indicaciones' => ['nullable', 'string', 'max', '2000'],
            'observaciones' => ['nullable', 'string', 'max', '2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo_consulta.required' => 'La evolucion necesita el motivo de la consulta.',
            'diagnostico.required' => 'La evolucion necesita un diagnostico.',
            'tratamiento.required' => 'La evolucion necesita el tratamiento indicado.',
        ];
    }
}