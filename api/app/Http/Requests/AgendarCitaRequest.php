<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EstadoCita;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos para agendar una cita.
 *
 * No se acepta `fin`: el fin se calcula con la duracion del tipo de atencion.
 * Aceptarlo seria abrir la puerta a citas de duracion arbitraria escrita a
 * mano, que es como se rompen las agendas en el mundo real.
 */
class AgendarCitaRequest extends FormRequest
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
            'paciente_id' => ['required', 'uuid'],
            'profesional_id' => ['required', 'uuid'],
            'sala_id' => ['nullable', 'uuid'],
            'tipo_atencion_id' => ['nullable', 'uuid'],
            'inicio' => ['required', 'date'],
            'estado' => ['sometimes', Rule::enum(EstadoCita::class)],
            'motivo_consulta' => ['nullable', 'string', 'max', '240'],
            'notas' => ['nullable', 'string', 'max', '500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'inicio.required' => 'La cita necesita una hora de inicio.',
            'inicio.date' => 'La hora de inicio no tiene un formato valido.',
            'paciente_id.required' => 'La cita necesita un paciente.',
            'profesional_id.required' => 'La cita necesita un profesional.',
        ];
    }
}