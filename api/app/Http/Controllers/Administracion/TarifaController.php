<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administracion;

use App\Http\Controllers\Controller;
use App\Models\Tarifa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tarifario de la clinica.
 */
class TarifaController extends Controller
{
    public function index(Request $peticion)
    {
        $this->authorize('viewAny', Tarifa::class);

        $tarifas = Tarifa::query()
            ->with('tipoAtencion:id,tenant_id,nombre')
            ->when($peticion->filled('tipo_atencion_id'), fn ($consulta) => $consulta->where('tipo_atencion_id', $peticion->query('tipo_atencion_id')))
            ->orderBy('concepto')
            ->get();

        return response()->json(['datos' => $tarifas]);
    }

    public function show(Tarifa $tarifa)
    {
        $this->authorize('view', $tarifa);

        return response()->json(['datos' => $tarifa->load('tipoAtencion')]);
    }

    public function store(Request $peticion): JsonResponse
    {
        $this->authorize('create', Tarifa::class);

        $datos = $peticion->validate([
            'tipo_atencion_id' => ['nullable', 'uuid'],
            'concepto' => ['required', 'string', 'max', '120'],
            'valor' => ['required', 'integer', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $tarifa = Tarifa::query()->create(array_merge($datos, [
            'tenant_id' => (string) $peticion->user()->tenant_id,
        ]));

        return response()->json(['datos' => $tarifa], 201);
    }

    public function update(Request $peticion, Tarifa $tarifa): JsonResponse
    {
        $this->authorize('update', $tarifa);

        $datos = $peticion->validate([
            'tipo_atencion_id' => ['sometimes', 'nullable', 'uuid'],
            'concepto' => ['sometimes', 'string', 'max', '120'],
            'valor' => ['sometimes', 'integer', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $tarifa->fill($datos);
        $tarifa->save();

        return response()->json(['datos' => $tarifa]);
    }
}