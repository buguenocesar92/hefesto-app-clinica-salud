<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administracion;

use App\Http\Controllers\Controller;
use App\Models\Profesional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Profesionales de la clinica.
 *
 * Ademas de la administracion, `index` lo necesita la agenda: al agendar hay
 * que elegir a quien se agenda. Por eso el listado abre para los tres roles
 * (ProfesionalPolicy::verParaAgenda) y el resto de las rutas no.
 */
class ProfesionalController extends Controller
{
    public function index(Request $peticion)
    {
        $this->autorizarListadoParaAgenda(Profesional::class);

        $profesionales = Profesional::query()
            ->when($peticion->query('activo') !== null, fn ($consulta) => $consulta->where('activo', $peticion->boolean('activo')))
            ->orderBy('nombre')
            ->get(['id', 'tenant_id', 'nombre', 'profesion', 'registro_profesional', 'correo', 'activo']);

        return response()->json([
            'datos' => $profesionales,
        ]);
    }

    public function show(Profesional $profesional)
    {
        $this->authorize('view', $profesional);

        return response()->json(['datos' => $profesional]);
    }

    public function store(Request $peticion): JsonResponse
    {
        $this->authorize('create', Profesional::class);

        $datos = $this->validar($peticion);

        $profesional = Profesional::query()->create(array_merge($datos, [
            'tenant_id' => (string) $peticion->user()->tenant_id,
        ]));

        return response()->json(['datos' => $profesional], 201);
    }

    public function update(Request $peticion, Profesional $profesional): JsonResponse
    {
        $this->authorize('update', $profesional);

        $profesional->fill($this->validar($peticion, parcial: true));
        $profesional->save();

        return response()->json(['datos' => $profesional]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $peticion, bool $parcial = false): array
    {
        $presente = $parcial ? 'sometimes' : 'required';

        return $peticion->validate([
            'nombre' => [$presente, 'string', 'max', '120'],
            'profesion' => [$presente, 'string', 'max', '80'],
            'registro_profesional' => ['nullable', 'string', 'max', '40'],
            'correo' => ['nullable', 'email', 'max', '120'],
            'activo' => ['sometimes', 'boolean'],
        ]);
    }
}