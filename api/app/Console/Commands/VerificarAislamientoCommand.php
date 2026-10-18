<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Clinica;
use App\Models\Paciente;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Comprueba, desde la consola, que la RLS esta puesta y funciona.
 *
 * Sirve para lo mismo que la suite `Aislamiento`, pero en el servidor: antes
 * de recibir pacientes reales, o despues de tocar una migracion.
 *
 *     php artisan clinica:verificar-aislamiento
 *
 * NO es una prueba unitaria: escribe filas de prueba y las borra al final.
 * No debe correrse contra una base con datos reales.
 */
class VerificarAislamientoCommand extends Command
{
    protected $signature = 'clinica:verificar-aislamiento
        {--clinica : Correlacion de una clinica existente para usar en la prueba. Si no se indica, se crea una clinica temporal.}';

    protected $description = 'Comprueba que el aislamiento entre clinicas funciona a nivel de base de datos.';

    private const TABLAS_PROTEGIDAS = [
        'profesionales',
        'salas',
        'tipos_atencion',
        'tarifas',
        'usuarios',
        'pacientes',
        'consentimientos',
        'citas',
        'bloqueos_agenda',
        'asignaciones_cuidado',
        'evoluciones',
        'recetas',
        'accesos_fichas',
    ];

    public function handle(ContextoTenant $contexto): int
    {
        $fallos = 0;

        $this->components->info('Revisando que las tablas de negocio tengan RLS forzado.');

        foreach (self::TABLAS_PROTEGIDAS as $tabla) {
            $fila = DB::selectOne(
                'SELECT relrowsecurity AS rls, relforcerowsecurity AS forzado FROM pg_class WHERE relname = ?',
                [$tabla]
            );

            if ($fila === null) {
                $this->components->error("La tabla {$tabla} no existe.");
                $fallos++;

                continue;
            }

            if (! $fila->rls || ! $fila->forzado) {
                $this->components->error("La tabla {$tabla} no tiene RLS forzado.");
                $fallos++;
            }
        }

        if ($fallos > 0) {
            $this->components->error('La instalacion NO tiene el aislamiento activo. No sigas.');

            return self::FAILURE;
        }

        $this->components->info('Las tablas tienen RLS forzado.');

        return $this->comprobarLecturaCruzada($contexto);
    }

    private function comprobarLecturaCruzada(ContextoTenant $contexto): int
    {
        $clinicaA = $this->obtenerOCrearClinica('verificacion-a');
        $clinicaB = $this->obtenerOCrearClinica('verificacion-b');

        $pacienteB = $contexto->ejecutarAislado($clinicaB->id, fn (): Paciente => Paciente::query()->create([
            'tenant_id' => $clinicaB->id,
            'rut' => '00.000.001-1',
            'nombres' => 'Paciente',
            'apellidos' => 'DeVerificacion',
            'fecha_nacimiento' => '1990-01-01',
        ]));

        $vistos = $contexto->ejecutarAislado($clinicaA->id, fn (): int => Paciente::query()
            ->whereKey($pacienteB->id)
            ->count());

        try {
            if ($vistos !== 0) {
                $this->components->error('La clinica A alcanzo a ver un paciente de la clinica B.');
                $this->components->error('El aislamiento NO funciona. Revise FORCE ROW LEVEL SECURITY.');

                return self::FAILURE;
            }

            $this->components->info('A no ve pacientes de B. Correcto.');
        } finally {
            $contexto->ejecutarAislado($clinicaB->id, fn () => $pacienteB->delete());
        }

        try {
            Paciente::query()->count();

            $this->components->error('Una consulta sin contexto devolvio filas en vez de fallar.');
            $this->components->error('La funcion public.app_clinica_id() deberia lanzar excepcion.');

            return self::FAILURE;
        } catch (Throwable) {
            $this->components->info('Sin contexto, la consulta falla. El aislamiento falla cerrado.');
        }

        $this->components->info('Aislamiento verificado.');

        return self::SUCCESS;
    }

    private function obtenerOCrearClinica(string $sufijo): Clinica
    {
        $correlacion = 'clinica-salud-' . $sufijo;

        return Clinica::query()->firstOrCreate(
            ['correlacion_identidad' => $correlacion],
            [
                'slug' => $correlacion,
                'nombre' => 'Temporal de verificacion (' . $sufijo . ')',
                'zona_horaria' => 'America/Santiago',
                'activo' => true,
            ]
        );
    }
}