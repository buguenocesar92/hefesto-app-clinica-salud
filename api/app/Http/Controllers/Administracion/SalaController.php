<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administracion;

use App\Http\Controllers\Controller;
use App\Models\Sala;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Salas de atencion.
 *
 * El listado abre para los tres roles, porque la agenda necesita saber que
 * salas hay (ver ProfesionalController).
 */
class SalaController extends Controller
{
    public function index(Request $peticion)
    {
        $this->autorizarListadoParaAgenda(Sala::class);

        $salas = Sala::query()
            ->when($peticion->query('activa') !== null, fn ($consulta) => $consulta->where('activa', $peticion->boolean('activa')))
            ->orderBy('nombre')
            ->get(['id', 'tenant_id', 'nombre', 'piso', 'activa']);

        return response()->json(['datos' => $salas]);
    }

    public function show(Sala $sala)
    {
        $this->authorize('view', $sala);

        return response()->json(['datos' => $sala]);
    }

    public function store(Request $peticion): JsonResponse
    {
        $this->authorize('create', Sala::class);

        $datos = $peticion->validate([
            'nombre' => ['required', 'string', 'max', '80'],
            'piso' => ['nullable', 'string', 'max', '20'],
            'activa' => ['sometimes', 'boolean'],
        ]);

        $sala = Sala::query()->create(array_merge($datos, [
            'tenant_id' => (string) $peticion->user()->tenant_id,
        ]));

        return response()->json(['datos' => $sala], 201);
    }

    public function update(Request $peticion, Sala $sala): JsonResponse
    {
        $this->authorize('update', $sala);

        $datos = $peticion->validate([
            'nombre' => ['sometimes', 'string', 'max', '80'],
            'piso' => ['sometimes', 'nullable', 'string', 'max', '20'],
            'activa' => ['sometimes', 'boolean'],
        ]);

        $sala->fill($datos);
        $sala->save();

        return response()->json(['datos' => $sala]);
    }
}