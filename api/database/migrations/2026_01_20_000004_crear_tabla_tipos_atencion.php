<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de atencion.
 *
 * `duracion_minutos` es la razon de existir de esta tabla: la cita toma su
 * duracion de aca y no de lo que escriba el cliente. Ver AgendarCitaRequest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_atencion', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->string('nombre', 80);
            $tabla->unsignedSmallInteger('duracion_minutos');
            $tabla->string('color', 7)->nullable();
            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();

            $tabla->foreign('tenant_id')->references('id')->on('clinicas')->cascadeOnDelete();
            $tabla->index(['tenant_id', 'activo']);
            $tabla->unique(['tenant_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_atencion');
    }
};