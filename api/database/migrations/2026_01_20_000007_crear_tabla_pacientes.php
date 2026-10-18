<?php

declare(strict_types=1);

use App\Enums\Sexo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pacientes: ficha de IDENTIFICACION.
 *
 * No aca hay informacion clinica. Lo clinico vive en `evoluciones`,
 * `recetas` y `citas`, y tiene reglas de acceso distintas. Esta tabla la ve
 * recepcion; las otras, solo el tratante.
 *
 * El RUT es unico DENTRO de la clinica: dos clinicas pueden tener al mismo
 * paciente, y en una plataforma de varias clinicas es normal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacientes', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->string('rut', 20);
            $tabla->string('nombres', 80);
            $tabla->string('apellidos', 80);
            $tabla->date('fecha_nacimiento');
            $tabla->string('sexo', 1)->nullable();
            $tabla->string('telefono', 30)->nullable();
            $tabla->string('correo', 120)->nullable();
            $tabla->string('direccion', 160)->nullable();
            $tabla->string('cobertura', 80)->nullable();
            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();

            $tabla->foreign('tenant_id')->references('id')->on('clinicas')->cascadeOnDelete();
            $tabla->unique(['tenant_id', 'rut']);
            $tabla->index(['tenant_id', 'activo']);
            $tabla->index(['tenant_id', 'apellidos']);
        });

        $sexos = array_map(
            static fn (Sexo $sexo): string => "'" . $sexo->value . "'",
            Sexo::cases()
        );

        DB::statement(
            'ALTER TABLE pacientes ADD CONSTRAINT pacientes_sexo_check CHECK (sexo IS NULL OR sexo IN (' . implode(', ', $sexos) . '))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('pacientes');
    }
};