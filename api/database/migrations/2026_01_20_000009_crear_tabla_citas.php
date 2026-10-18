<?php

declare(strict_types=1);

use App\Enums\EstadoCita;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Citas de la agenda.
 *
 * `inicio` y `fin` son `timestamp with time zone`: PostgreSQL guarda el
 * instante y lo convierte al mostrar. Guardarlos sin zona es la causa clasica
 * de que la agenda se corra una hora en el cambio de hora de verano chileno.
 *
 * INDICES UNICOS PARCIALES
 * ------------------------
 * No se puede agendar dos veces al mismo profesional (o en la misma sala) en
 * el mismo minuto. Esto se asegura en tres capas:
 *   1. La aplicacion revisa solapamiento (VerificadorDeDisponibilidad).
 *   2. Estos indices unicos lo impiden en la base, aunque dos peticiones
 *      lleguen a la vez.
 *   3. La RLS impide que el indice decida sobre filas de otra clinica.
 *
 * El indice es PARCIAL (`WHERE estado IN (...)`): una cita atendida queda en
 * el historico y no debe bloquear el mismo minuto para siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->uuid('paciente_id');
            $tabla->uuid('profesional_id');
            $tabla->uuid('sala_id')->nullable();
            $tabla->uuid('tipo_atencion_id')->nullable();
            $tabla->timestampTz('inicio');
            $tabla->timestampTz('fin');
            $tabla->string('estado', 20);
            $tabla->string('motivo_consulta', 240)->nullable();
            $tabla->string('motivo_cancelacion', 240)->nullable();
            $tabla->string('notas', 500)->nullable();
            $tabla->uuid('creado_por_usuario_id')->nullable();
            $tabla->timestampsTz();

            $tabla->foreign('tenant_id')->references('id')->on('clinicas')->cascadeOnDelete();
            $tabla->foreign('paciente_id')->references('id')->on('pacientes')->cascadeOnDelete();
            $tabla->foreign('profesional_id')->references('id')->on('profesionales')->restrictOnDelete();
            $tabla->foreign('sala_id')->references('id')->on('salas')->nullOnDelete();
            $tabla->foreign('tipo_atencion_id')->references('id')->on('tipos_atencion')->nullOnDelete();
            $tabla->foreign('creado_por_usuario_id')->references('id')->on('usuarios')->nullOnDelete();

            $tabla->index(['tenant_id', 'inicio']);
            $tabla->index(['tenant_id', 'profesional_id', 'inicio']);
            $tabla->index(['tenant_id', 'sala_id', 'inicio']);
            $tabla->index(['tenant_id', 'paciente_id', 'inicio']);
        });

        $estados = array_map(
            static fn (EstadoCita $estado): string => "'" . $estado->value . "'",
            EstadoCita::cases()
        );

        DB::statement('ALTER TABLE citas ADD CONSTRAINT citas_estado_check CHECK (estado IN (' . implode(', ', $estados) . '))');

        $reservan = "estado IN ('agendada', 'confirmada')";

        DB::statement(
            'CREATE UNIQUE INDEX citas_profesional_inicio_unico ON citas (tenant_id, profesional_id, inicio) WHERE ' . $reservan
        );

        DB::statement(
            'CREATE UNIQUE INDEX citas_sala_inicio_unico ON citas (tenant_id, sala_id, inicio) WHERE ' . $reservan . ' AND sala_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('citas');
    }
};