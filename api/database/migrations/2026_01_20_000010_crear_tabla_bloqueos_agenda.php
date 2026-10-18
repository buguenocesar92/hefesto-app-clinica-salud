<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bloqueos de agenda: feriados, almuerzo, vacaciones, reuniones.
 *
 * Si `profesional_id` y `sala_id` son nulos, el bloqueo es de toda la clinica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bloqueos_agenda', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->uuid('profesional_id')->nullable();
            $tabla->uuid('sala_id')->nullable();
            $tabla->timestampTz('inicio');
            $tabla->timestampTz('fin');
            $tabla->string('motivo', 160);
            $tabla->uuid('creado_por_usuario_id')->nullable();
            $tabla->timestampsTz();

            $tabla->foreign('tenant_id')->references('id')->on('clinicas')->cascadeOnDelete();
            $tabla->foreign('profesional_id')->references('id')->on('profesionales')->cascadeOnDelete();
            $tabla->foreign('sala_id')->references('id')->on('salas')->cascadeOnDelete();
            $tabla->foreign('creado_por_usuario_id')->references('id')->on('usuarios')->nullOnDelete();
            $tabla->index(['tenant_id', 'inicio', 'fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloqueos_agenda');
    }
};