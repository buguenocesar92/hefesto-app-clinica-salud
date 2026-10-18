<?php

declare(strict_types=1);

use App\Enums\RolClinico;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuentas de la clinica.
 *
 * `rol` tiene restriccion de dominio en la base, no solo en PHP. Un rol mal
 * escrito por error (o escrito a mano con SQL) tiene que ser imposible de
 * guardar: los permisos de esta aplicacion dependen de esta columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->string('nombre', 120);
            $tabla->string('correo', 120);
            $tabla->string('rol', 20);
            $tabla->uuid('profesional_id')->nullable();
            $tabla->boolean('activo')->default(false);
            $tabla->timestampTz('ultimo_ingreso_en')->nullable();
            $tabla->rememberToken();
            $tabla->timestampsTz();

            $tabla->foreign('tenant_id')->references('id')->on('clinicas')->cascadeOnDelete();
            $tabla->foreign('profesional_id')->references('id')->on('profesionales')->nullOnDelete();
            $tabla->unique(['tenant_id', 'correo']);
            $tabla->index(['tenant_id', 'rol']);
        });

        $roles = array_map(
            static fn (RolClinico $rol): string => "'" . $rol->value . "'",
            RolClinico::cases()
        );

        DB::statement(
            'ALTER TABLE usuarios ADD CONSTRAINT usuarios_rol_check CHECK (rol IN (' . implode(', ', $roles) . '))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};