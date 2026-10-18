<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarifario de la clinica.
 *
 * El valor es un entero en pesos chilenos: sin decimales. Ver
 * App\Models\Tarifa para por que no se usa float.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->uuid('tipo_atencion_id')->nullable();
            $tabla->string('concepto', 120);
            $tabla->integer('valor');
            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();

            $tabla->foreign('tenant_id')->references('id')->on('clinicas')->cascadeOnDelete();
            $tabla->foreign('tipo_atencion_id')->references('id')->on('tipos_atencion')->nullOnDelete();
            $tabla->index(['tenant_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifas');
    }
};