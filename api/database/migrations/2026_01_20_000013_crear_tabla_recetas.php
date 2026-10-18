<?php

declare(strict_types=1);

use App\Enums\EstadoReceta;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recetas y certificados.
 *
 * `documento_referencia_externo` es la referencia del documento firmado en
 * DocuSeal. ClinicaSalud arma el texto y el formato imprimible; la firma la
 * hace DocuSeal. Implementar firma criptografica aca seria duplicar lo que ya
 * existe en el catalogo de Hefesto. Ver docs/03.
 *
 * La receta no se borra: se anula. Ver App\Enums\EstadoReceta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recetas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->uuid('paciente_id');
            $tabla->uuid('profesional_id');
            $tabla->uuid('cita_id')->nullable();
            $tabla->timestampTz('fecha');
            $tabla->text('contenido');
            $tabla->string('estado', 20);
            $tabla->timestampTz('emitida_en')->nullable();
            $tabla->string('documento_referencia_externo', 120)->nullable();
            $tabla->timestampsTz();

            $tabla->foreign('tenant_id')->references('id')->on('clinicas')->cascadeOnDelete();
            $tabla->foreign('paciente_id')->references('id')->on('pacientes')->cascadeOnDelete();
            $tabla->foreign('profesional_id')->references('id')->on('profesionales')->restrictOnDelete();
            $tabla->foreign('cita_id')->references('id')->on('citas')->nullOnDelete();

            $tabla->index(['tenant_id', 'paciente_id', 'fecha']);
            $tabla->index(['tenant_id', 'profesional_id', 'fecha']);
        });

        $estados = array_map(
            static fn (EstadoReceta $estado): string => "'" . $estado->value . "'",
            EstadoReceta::cases()
        );

        DB::statement('ALTER TABLE recetas ADD CONSTRAINT recetas_estado_check CHECK (estado IN (' . implode(', ', $estados) . '))');
    }

    public function down(): void
    {
        Schema::dropIfExists('recetas');
    }
};