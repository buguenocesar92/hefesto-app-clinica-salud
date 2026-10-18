<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Profesional;
use App\Services\Agenda\VerificadorDeDisponibilidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * Huecos libres de un profesional en un dia.
 *
 * Responde la pregunta que se hace recepcion todo el dia: "a que hora puedo
 * poner a este paciente". Se arma la jornada completa (por configuracion, de
 * 08:00 a 18:00), se le restan las citas que ya existen y los bloqueos, y se
 * devuelven los tramos libres con la duracion pedida.
 *
 * No escribe nada: es una consulta de apoyo para la pantalla de agenda.
 */
class DisponibilidadController extends Controller
{
    /** @var array<string, string> */
    private const HORARIO_POR_DEFECTO = [
        'inicio' => '08:00',
        'fin' => '18:00',
    ];

    public function __construct(private readonly VerificadorDeDisponibilidad $verificador) {}

    public function __invoke(Request $peticion): JsonResponse
    {
        $this->authorize('viewAny', Cita::class);

        $datos = $peticion->validate([
            'profesional_id' => ['required', 'uuid'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'duracion_minutos' => ['nullable', 'integer', 'min:5', 'max:240'],
        ]);

        $clinicaId = (string) $peticion->user()->tenant_id;

        $profesional = Profesional::query()
            ->where('tenant_id', $clinicaId)
            ->whereKey($datos['profesional_id'])
            ->where('activo', true)
            ->first();

        if ($profesional === null) {
            // Mismo criterio que en el resto de la API: no se confirma que el
            // profesional exista en otra clinica.
            abort(404, 'No encontrado.');
        }

        $dia = Carbon::parse($datos['fecha'])->startOfDay();
        $duracion = (int) ($datos['duracion_minutos'] ?? 30);

        $inicioJornada = $dia->copy()->setTimeFromTimeString(self::HORARIO_POR_DEFECTO['inicio']);
        $finJornada = $dia->copy()->setTimeFromTimeString(self::HORARIO_POR_DEFECTO['fin']);

        $ocupadas = Cita::query()
            ->where('tenant_id', $clinicaId)
            ->where('profesional_id', $profesional->id)
            ->whereIn('estado', ['agendada', 'confirmada', 'atendida'])
            ->where('inicio', '<', $finJornada)
            ->where('fin', '>', $inicioJornada)
            ->orderBy('inicio')
            ->get(['inicio', 'fin']);

        $huecos = [];
        $cursor = $inicioJornada->copy();

        foreach ($ocupadas as $cita) {
            if ($cursor->greaterThanOrEqualTo($cita->inicio)) {
                $cursor = Carbon::parse($cita->fin);

                continue;
            }

            if ($cursor->copy()->addMinutes($duracion)->lessThanOrEqualTo($cita->inicio)) {
                $huecos[] = $this->hueco($cursor, $duracion);
            }

            $cursor = Carbon::parse($cita->fin);
        }

        while ($cursor->copy()->addMinutes($duracion)->lessThanOrEqualTo($finJornada)) {
            $huecos[] = $this->hueco($cursor, $duracion);
            $cursor->addMinutes($duracion);
        }

        return response()->json([
            'profesional_id' => $profesional->id,
            'fecha' => $dia->toDateString(),
            'duracion_minutos' => $duracion,
            'jornada' => [
                'inicio' => $inicioJornada->toIso8601String(),
                'fin' => $finJornada->toIso8601String(),
            ],
            'huecos' => $huecos,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function hueco(Carbon $inicio, int $duracion): array
    {
        return [
            'inicio' => $inicio->toIso8601String(),
            'fin' => $inicio->copy()->addMinutes($duracion)->toIso8601String(),
        ];
    }
}