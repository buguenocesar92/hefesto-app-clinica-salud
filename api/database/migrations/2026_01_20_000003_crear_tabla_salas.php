<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salas de atencion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->string('nombre', 80);
            $tabla->string('piso', 20)->nullable();
            $tabla->boolean('activa')->default(true);
            $tabla->timestampsTz();

            $tabla->foreign('tenant_id')->references('id')->on('clinicas')->cascadeOnDelete();
            $tabla->index(['tenant_id', 'activa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salas');
    }
};