<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evoluciones: la historia clinica escrita.
 *
 * `codigo_cie10` es texto. La aplicacion NO trae el catalogo CIE-10 del
 * Ministerio de Salud, asi que no puede validar que el codigo exista. Poner
 * una validacion inventada seria peor que ninguna: dejaria pasar datos malos
 * con apariencia de buenos. Queda declarado como pendiente en docs/10.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evoluciones', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->uuid('paciente_id');
            $tabla->uuid('profesional_id');
            $tabla->uuid('cita_id')->nullable();
            $tabla->timestampTz('fecha');
            $tabla->string('motivo_consulta', 240);
            $tabla->string('codigo_cie10', 16)->nullable();
            $tabla->text('diagnostico');
            $tabla->text('tratamiento');
            $tabla->text('indicaciones')->nullable();
            $tabla->text('observaciones')->nullable();
            $tabla->timestampsTz();

            $tabla->foreign('tenant_id')->references('id')->on('clinicas')->cascadeOnDelete();
            $tabla->foreign('paciente_id')->references('id')->on('pacientes')->cascadeOnDelete();
            $tabla->foreign('profesional_id')->references('id')->on('profesionales')->restrictOnDelete();
            $tabla->foreign('cita_id')->references('id')->on('citas')->nullOnDelete();

            $tabla->index(['tenant_id', 'paciente_id', 'fecha']);
            $tabla->index(['tenant_id', 'profesional_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evoluciones');
    }
};