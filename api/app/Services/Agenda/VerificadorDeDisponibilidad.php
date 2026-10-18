<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Models\BloqueoAgenda;
use App\Models\Cita;
use App\Models\Profesional;
use App\Models\Sala;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Revisa que un bloque de la agenda este libre.
 *
 * Dos recursos se agendan: el profesional y la sala. Los dos pueden chocar:
 * una sala ocupada con el profesional libre, o al reves.
 *
 * SE USA UNA EXCLUSION POR IDENTIFICADOR, NO POR RANGO
 * ---------------------------------------------------
 * Para agendar una cita nueva se excluye del conteo la cita que se esta
 * moviendo. Con un filtro de rango, mover una cita se cruzaria consigo misma.
 *
 * El filtro de solapamiento es `inicio < fin_buscado AND fin > inicio_buscado`,
 * que es la forma correcta de detectar solapamiento en dos intervalos
 * semiabiertos: `[inicio, fin)`. Dos citas que se tocan exactamente (una
 * termina cuando la otra empieza) NO se chocan.
 *
 * Todo se evalua dentro del contexto de clinica de la peticion, asi que las
 * consultas heredan la RLS y nunca miran citas de otra clinica.
 */
class VerificadorDeDisponibilidad
{
    public function profesionalEstaLibre(
        string $clinicaId,
        string $profesionalId,
        CarbonInterface $inicio,
        CarbonInterface $fin,
        ?string $citaExcluida = null,
    ): bool {
        $choque = Cita::query()
            ->where('tenant_id', $clinicaId)
            ->where('profesional_id', $profesionalId)
            ->whereIn('estado', ['agendada', 'confirmada', 'atendida'])
            ->where('inicio', '<', $fin)
            ->where('fin', '>', $inicio)
            ->when($citaExcluida !== null, fn ($consulta) => $consulta->whereKeyNot($citaExcluida))
            ->exists();

        return ! $choque && ! $this->estaBloqueado($clinicaId, $inicio, $fin, $profesionalId, null);
    }

    public function salaEstaLibre(
        string $clinicaId,
        string $salaId,
        CarbonInterface $inicio,
        CarbonInterface $fin,
        ?string $citaExcluida = null,
    ): bool {
        $choque = Cita::query()
            ->where('tenant_id', $clinicaId)
            ->where('sala_id', $salaId)
            ->whereIn('estado', ['agendada', 'confirmada', 'atendida'])
            ->where('inicio', '<', $fin)
            ->where('fin', '>', $inicio)
            ->when($citaExcluida !== null, fn ($consulta) => $consulta->whereKeyNot($citaExcluida))
            ->exists();

        return ! $choque && ! $this->estaBloqueado($clinicaId, $inicio, $fin, null, $salaId);
    }

    /**
     * Bloqueos de agenda: feriado, almuerzo, vacaciones.
     */
    private function estaBloqueado(
        string $clinicaId,
        CarbonInterface $inicio,
        CarbonInterface $fin,
        ?string $profesionalId,
        ?string $salaId,
    ): bool {
        return BloqueoAgenda::query()
            ->where('tenant_id', $clinicaId)
            ->where('inicio', '<', $fin)
            ->where('fin', '>', $inicio)
            ->where(function ($consulta) use ($profesionalId, $salaId): void {
                $consulta->whereNull('profesional_id')->whereNull('sala_id');

                if ($profesionalId !== null) {
                    $consulta->orWhere('profesional_id', $profesionalId);
                }

                if ($salaId !== null) {
                    $consulta->orWhere('sala_id', $salaId);
                }
            })
            ->exists();
    }

    /**
     * Calcula el fin de una cita a partir del inicio y del tipo de atencion.
     *
     * La duracion vive en el tipo de atencion, no en la peticion: si el
     * cliente dijera "30 minutos" para un tipo de atencion de 45, la agenda
     * quedaria mal y las horas de la clinica se solaparian.
     */
    public function calcularFin(CarbonInterface $inicio, int $duracionMinutos): CarbonImmutable
    {
        return CarbonImmutable::instance($inicio)->addMinutes($duracionMinutos);
    }

    /**
     * Comprueba que el profesional y la sala existan y esten activos en esta
     * clinica. La RLS ya limita las filas, pero un profesional desactivado no
     * debe recibir citas aunque la fila siga visible.
     */
    public function profesionalActivo(string $clinicaId, string $profesionalId): ?Profesional
    {
        return Profesional::query()
            ->where('tenant_id', $clinicaId)
            ->whereKey($profesionalId)
            ->where('activo', true)
            ->first();
    }

    public function salaActiva(string $clinicaId, string $salaId): ?Sala
    {
        return Sala::query()
            ->where('tenant_id', $clinicaId)
            ->whereKey($salaId)
            ->where('activa', true)
            ->first();
    }
}