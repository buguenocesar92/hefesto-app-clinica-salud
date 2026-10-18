<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asignaciones de cuidado.
 *
 * "Este profesional atiende a este paciente", registrado por la clinica para
 * los casos en que hay un equipo de salud definido. Es una de las tres formas
 * de ser tratante (ver App\Services\Permisos\EquipoDeCuidado).
 *
 * La fila no se borra al terminar el episodio: se desactiva. Asi se puede
 * explicar despues por que alguien tuvo acceso a una ficha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones_cuidado', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->uuid('paciente_id');
            $tabla->uuid('profesional_id');
            $tabla->boolean('activo')->default(true);
            $tabla->string('motivo', 200)->nullable();
            $tabla->timestampsTz();

            $tabla->foreign('tenant_id')->references('id')->on('clinicas')->cascadeOnDelete();
            $tabla->foreign('paciente_id')->references('id')->on('pacientes')->cascadeOnDelete();
            $tabla->foreign('profesional_id')->references('id')->on('profesionales')->cascadeOnDelete();
            $tabla->unique(['paciente_id', 'profesional_id']);
            $tabla->index(['tenant_id', 'profesional_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_cuidado');
    }
};