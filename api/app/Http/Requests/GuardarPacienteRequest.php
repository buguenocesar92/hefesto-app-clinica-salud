<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Sexo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos para registrar un paciente.
 *
 * OJO: esta clase NO valida nada clinico. Los datos que validan son los de
 * identificacion. El motivo de consulta y el diagnostico no pasan por aca:
 * viven en la ficha clinica.
 */
class GuardarPacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La capacidad real la revisa PacientePolicy en el controlador. Acá se
        // devuelve true para que la validacion no se ejecute antes del permiso.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rut' => ['required', 'string', 'max', '20', 'regex:/^[0-9Kk.-]{7,20}$/'],
            'nombres' => ['required', 'string', 'max', '80'],
            'apellidos' => ['required', 'string', 'max', '80'],
            'fecha_nacimiento' => ['required', 'date_format:Y-m-d', 'before:today'],
            'sexo' => ['nullable', Rule::enum(Sexo::class)],
            'telefono' => ['nullable', 'string', 'max', '30'],
            'correo' => ['nullable', 'email', 'max', '120'],
            'direccion' => ['nullable', 'string', 'max', '160'],
            'cobertura' => ['nullable', 'string', 'max', '80'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rut.regex' => 'El RUT solo puede contener numeros, la letra K y los separadores . y -.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento no puede ser futura.',
            'rut.unique' => 'Ya existe un paciente con ese RUT en esta clinica.',
        ];
    }
}