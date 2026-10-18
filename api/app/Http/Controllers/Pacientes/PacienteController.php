<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pacientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarPacienteRequest;
use App\Http\Resources\PacienteResource;
use App\Models\Paciente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ficha de identificacion de pacientes.
 *
 * ACESIBLE PARA LOS TRES ROLES. Aqui esta el otro lado del producto: recepcion
 * necesita saber a quien agenda, con que telefono y con que cobertura. Eso es
 * dato personal, no dato de salud.
 *
 * La historia clinica de estos mismos pacientes vive en otra ruta
 * (`fichas/{paciente}`), que exige ser profesional tratante. Ver docs/03.
 */
class PacienteController extends Controller
{
    public function index(Request $peticion)
    {
        $this->authorize('viewAny', Paciente::class);

        $peticion->validate([
            'buscar' => ['nullable', 'string', 'max', '80'],
            'activo' => ['nullable', 'boolean'],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $porPagina = (int) ($peticion->query('por_pagina') ?? 25);

        $pacientes = Paciente::query()
            ->when($peticion->filled('buscar'), function ($consulta) use ($peticion): void {
                $texto = '%' . str_replace('%', '\%', (string) $peticion->query('buscar')) . '%';

                $consulta->where(function ($interna) use ($texto): void {
                    $interna->where('nombres', 'ilike', $texto)
                        ->orWhere('apellidos', 'ilike', $texto)
                        ->orWhere('rut', 'ilike', $texto);
                });
            })
            ->when($peticion->has('activo'), fn ($consulta) => $consulta->where('activo', $peticion->boolean('activo')))
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->paginate($porPagina);

        return PacienteResource::collection($pacientes);
    }

    public function show(Paciente $paciente)
    {
        $this->authorize('view', $paciente);

        $paciente->loadCount('citas');

        return new PacienteResource($paciente);
    }

    public function store(GuardarPacienteRequest $peticion): JsonResponse
    {
        $this->authorize('create', Paciente::class);

        $datos = $peticion->validated();
        $clinicaId = (string) $peticion->user()->tenant_id;

        if (Paciente::query()->where('tenant_id', $clinicaId)->where('rut', $datos['rut'])->exists()) {
            return response()->json([
                'mensaje' => 'Ya existe un paciente con ese RUT en esta clinica.',
                'errores' => ['rut' => ['Ya existe un paciente con ese RUT en esta clinica.']],
            ], 422);
        }

        $paciente = Paciente::query()->create(array_merge($datos, [
            'tenant_id' => $clinicaId,
        ]));

        return (new PacienteResource($paciente))->response()->setStatusCode(201);
    }

    public function update(GuardarPacienteRequest $peticion, Paciente $paciente): JsonResponse
    {
        $this->authorize('update', $paciente);

        $datos = $peticion->validated();
        $clinicaId = (string) $peticion->user()->tenant_id;

        $repetido = Paciente::query()
            ->where('tenant_id', $clinicaId)
            ->where('rut', $datos['rut'])
            ->whereKeyNot($paciente->id)
            ->exists();

        if ($repetido) {
            return response()->json([
                'mensaje' => 'Ya existe otro paciente con ese RUT en esta clinica.',
                'errores' => ['rut' => ['Ya existe otro paciente con ese RUT en esta clinica.']],
            ], 422);
        }

        $paciente->fill($datos);
        $paciente->save();

        return (new PacienteResource($paciente))->response();
    }
}