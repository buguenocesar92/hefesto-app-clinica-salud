<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administracion;

use App\Http\Controllers\Controller;
use App\Models\TipoAtencion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tipos de atencion y su duracion.
 *
 * La duracion es lo que hace util la agenda: al agendar, la cita toma su
 * tiempo del tipo de atencion y no de lo que se escriba a mano.
 */
class TipoAtencionController extends Controller
{
    public function index(Request $peticion)
    {
        $this->autorizarListadoParaAgenda(TipoAtencion::class);

        $tipos = TipoAtencion::query()
            ->orderBy('nombre')
            ->get(['id', 'tenant_id', 'nombre', 'duracion_minutos', 'color', 'activo']);

        return response()->json(['datos' => $tipos]);
    }

    public function show(TipoAtencion $tipoAtencion)
    {
        $this->authorize('view', $tipoAtencion);

        return response()->json(['datos' => $tipoAtencion]);
    }

    public function store(Request $peticion): JsonResponse
    {
        $this->authorize('create', TipoAtencion::class);

        $datos = $peticion->validate([
            'nombre' => ['required', 'string', 'max', '80'],
            'duracion_minutos' => ['required', 'integer', 'min:5', 'max:' . (int) config('clinica.duracion_maxima_minutos', 240)],
            'color' => ['nullable', 'string', 'max', '7'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $tipo = TipoAtencion::query()->create(array_merge($datos, [
            'tenant_id' => (string) $peticion->user()->tenant_id,
        ]));

        return response()->json(['datos' => $tipo], 201);
    }

    public function update(Request $peticion, TipoAtencion $tipoAtencion): JsonResponse
    {
        $this->authorize('update', $tipoAtencion);

        $datos = $peticion->validate([
            'nombre' => ['sometimes', 'string', 'max', '80'],
            'duracion_minutos' => ['sometimes', 'integer', 'min:5', 'max:' . (int) config('clinica.duracion_maxima_minutos', 240)],
            'color' => ['sometimes', 'nullable', 'string', 'max', '7'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $tipoAtencion->fill($datos);
        $tipoAtencion->save();

        return response()->json(['datos' => $tipoAtencion]);
    }
}