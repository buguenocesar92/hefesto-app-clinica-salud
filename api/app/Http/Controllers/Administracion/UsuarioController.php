<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administracion;

use App\Enums\RolClinico;
use App\Http\Controllers\Controller;
use App\Models\Profesional;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cuentas de la clinica.
 *
 * Un administrador puede crear receptoras y profesionales, y tambien
 * administrar a otros administradores. NO puede crear la PRIMERA cuenta de
 * administracion: esa la deja el despliegue, con el comando
 * `clinica:crear-administrador` (ver docs/04).
 *
 * La cuenta nueva se crea INACTIVA y con una marca `pendiente`. La activacion
 * pasa por el correo: sin ese paso, un administrador con acceso al panel
 * podria dar de alta a cualquiera con cualquier correo y empezar a operar en
 * nombre de esa persona. Ver docs/03 y docs/06.
 */
class UsuarioController extends Controller
{
    public function index(Request $peticion)
    {
        $this->authorize('viewAny', Usuario::class);

        $usuarios = Usuario::query()
            ->with('profesional:id,tenant_id,nombre')
            ->when($peticion->filled('rol'), fn ($consulta) => $consulta->where('rol', $peticion->query('rol')))
            ->orderBy('nombre')
            ->get(['id', 'tenant_id', 'nombre', 'correo', 'rol', 'profesional_id', 'activo', 'ultimo_ingreso_en']);

        return response()->json(['datos' => $usuarios]);
    }

    public function show(Usuario $usuario)
    {
        $this->authorize('view', $usuario);

        return response()->json(['datos' => $usuario->load('profesional')]);
    }

    public function store(Request $peticion): JsonResponse
    {
        $this->authorize('create', Usuario::class);

        $datos = $peticion->validate([
            'nombre' => ['required', 'string', 'max', '120'],
            'correo' => ['required', 'email', 'max', '120'],
            'rol' => ['required', Rule::enum(RolClinico::class)],
            'profesional_id' => ['nullable', 'uuid'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $clinicaId = (string) $peticion->user()->tenant_id;

        if (Usuario::query()->where('tenant_id', $clinicaId)->where('correo', mb_strtolower($datos['correo']))->exists()) {
            return response()->json([
                'mensaje' => 'Ya existe una cuenta con ese correo en esta clinica.',
            ], 422);
        }

        $profesionalId = $this->resolverProfesional($clinicaId, $datos['rol'], $datos['profesional_id'] ?? null);

        $usuario = Usuario::query()->create([
            'tenant_id' => $clinicaId,
            'nombre' => $datos['nombre'],
            'correo' => mb_strtolower($datos['correo']),
            'rol' => $datos['rol'],
            'profesional_id' => $profesionalId,
            'activo' => (bool) ($datos['activo'] ?? false),
        ]);

        return response()->json(['datos' => $usuario], 201);
    }

    public function update(Request $peticion, Usuario $usuario): JsonResponse
    {
        $this->authorize('update', $usuario);

        $clinicaId = (string) $peticion->user()->tenant_id;

        $datos = $peticion->validate([
            'nombre' => ['sometimes', 'string', 'max', '120'],
            'rol' => ['sometimes', Rule::enum(RolClinico::class)],
            'profesional_id' => ['sometimes', 'nullable', 'uuid'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        if (($datos['activo'] ?? null) === false && $usuario->rol === RolClinico::Administrador) {
            return response()->json([
                'mensaje' => 'No se puede desactivar a un administrador. Use la consola para revisar que quede al menos uno.',
            ], 422);
        }

        $rol = $datos['rol'] ?? $usuario->rol->value;
        $profesionalId = array_key_exists('profesional_id', $datos)
            ? $this->resolverProfesional($clinicaId, $rol, $datos['profesional_id'])
            : $usuario->profesional_id;

        if (isset($datos['nombre'])) {
            $usuario->nombre = $datos['nombre'];
        }

        $usuario->rol = $rol;
        $usuario->profesional_id = $profesionalId;

        if (array_key_exists('activo', $datos)) {
            $usuario->activo = (bool) $datos['activo'];
        }

        $usuario->save();

        return response()->json(['datos' => $usuario->fresh()]);
    }

    /**
     * Un usuario con rol profesional TIENE que apuntar a una ficha de
     * profesional. Sin ella no podria leer ninguna ficha clinica, y la
     * aplicacion quedaria con cuentas que parecen profesionales y no lo son.
     *
     * @param  mixed  $rol
     */
    private function resolverProfesional(string $clinicaId, mixed $rol, ?string $profesionalId): ?string
    {
        $esProfesional = $rol instanceof RolClinico
            ? $rol === RolClinico::Profesional
            : $rol === RolClinico::Profesional->value;

        if (! $esProfesional) {
            return null;
        }

        $profesional = Profesional::query()
            ->where('tenant_id', $clinicaId)
            ->when($profesionalId !== null, fn ($consulta) => $consulta->whereKey($profesionalId))
            ->first();

        if ($profesional === null) {
            abort(422, 'El rol profesional necesita una ficha de profesional de esta clinica.');
        }

        return $profesional->id;
    }
}