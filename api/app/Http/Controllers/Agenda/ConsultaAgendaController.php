<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Http\Resources\CitaResource;
use App\Models\Cita;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * La agenda del dia o del rango pedido.
 *
 * Los tres roles entran: agendar es una capacidad de mostrador. Lo que se
 * muestra es nombre del paciente, profesional, sala, hora y estado: nada de
 * la historia clinica.
 */
class ConsultaAgendaController extends Controller
{
    public function __invoke(Request $peticion): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Cita::class);

        $desde = $this->fecha($peticion->query('desde'), now()->startOfDay());
        $hasta = $this->fecha($peticion->query('hasta'), now()->endOfDay());

        if ($desde->greaterThan($hasta)) {
            return CitaResource::collection(
                Cita::query()->whereRaw('1 = 0')->get()
            )->additional(['mensaje' => 'El rango de fechas esta invertido.']);
        }

        $citas = Cita::query()
            ->with([
                'paciente:id,tenant_id,nombres,apellidos,telefono',
                'profesional:id,tenant_id,nombre',
                'sala:id,tenant_id,nombre',
                'tipoAtencion:id,tenant_id,nombre',
            ])
            ->where('inicio', '>=', $desde)
            ->where('inicio', '<=', $hasta)
            ->when($peticion->filled('profesional_id'), fn ($consulta) => $consulta->where('profesional_id', $peticion->query('profesional_id')))
            ->when($peticion->filled('sala_id'), fn ($consulta) => $consulta->where('sala_id', $peticion->query('sala_id')))
            ->when($peticion->filled('estado'), fn ($consulta) => $consulta->where('estado', $peticion->query('estado')))
            ->orderBy('inicio')
            ->get();

        return CitaResource::collection($citas);
    }

    /**
     * Lee una fecha de la consulta. Si no viene, usa la que se le pase.
     */
    private function fecha(?string $valor, Carbon $porDefecto): Carbon
    {
        if ($valor === null || $valor === '') {
            return $porDefecto;
        }

        try {
            return Carbon::parse($valor);
        } catch (\Throwable) {
            abort(422, 'Fecha no valida: ' . $valor);
        }
    }
}