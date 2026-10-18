<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administracion;

use App\Enums\ResultadoAcceso;
use App\Http\Controllers\Controller;
use App\Models\AccesoFicha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bitacora de accesos a ficha clinica.
 *
 * Es la pantalla que el administrador revisa cuando: hay una denuncia, una
 * duda de un paciente, o una alerta por demasiados intentos denegados.
 *
 * Se muestra en orden cronologico inverso y trae el nombre de quien abrio cada
 * ficha. Quien puede leer esta pantalla puede saber cuales son las personas
 * que hay en la clinica y que historias clinicas estas leyendo. Esa es una
 * responsabilidad concreta: por eso la pantalla es solo de lectura, y su propia
 * consulta queda en el registro del servidor (no en `accesos_fichas`, que es
 * para las fichas clinicas, no para la lectura de la bitacora).
 */
class AuditoriaController extends Controller
{
    public function __invoke(Request $peticion): JsonResponse
    {
        $clinicaId = (string) $peticion->user()->tenant_id;

        $datos = $peticion->validate([
            'paciente_id' => ['nullable', 'uuid'],
            'usuario_id' => ['nullable', 'uuid'],
            'resultado' => ['nullable'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        if (isset($datos['resultado']) && $datos['resultado'] !== '') {
            $this->validarResultado((string) $datos['resultado']);
        }

        $registros = AccesoFicha::query()
            ->with('usuario:id,tenant_id,nombre,correo')
            ->where('tenant_id', $clinicaId)
            ->when($datos['paciente_id'] ?? null, fn ($consulta, $valor) => $consulta->where('paciente_id', $valor))
            ->when($datos['usuario_id'] ?? null, fn ($consulta, $valor) => $consulta->where('usuario_id', $valor))
            ->when($datos['resultado'] ?? null, fn ($consulta, $valor) => $consulta->where('resultado', $valor))
            ->when($datos['desde'] ?? null, fn ($consulta, $valor) => $consulta->where('registrado_en', '>=', $valor))
            ->when($datos['hasta'] ?? null, fn ($consulta, $valor) => $consulta->where('registrado_en', '<=', $valor))
            ->orderByDesc('registrado_en')
            ->paginate((int) ($datos['por_pagina'] ?? 50));

        return response()->json([
            'datos' => $registros->items(),
            'paginacion' => [
                'pagina' => $registros->currentPage(),
                'por_pagina' => $registros->perPage(),
                'total' => $registros->total(),
                'paginas' => $registros->lastPage(),
            ],
        ]);
    }

    private function validarResultado(string $valor): void
    {
        if (ResultadoAcceso::tryFrom($valor) === null) {
            abort(422, 'Resultado de acceso desconocido: ' . $valor);
        }
    }
}