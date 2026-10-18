<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clinicas: el tenant de esta aplicacion.
 *
 * Es la UNICA tabla sin `tenant_id` y la unica sin Row Level Security: cada
 * fila es una clinica completa y hay que poder leerla (nombre, zona horaria)
 * antes de que exista un contexto de clinica que filtrar.
 *
 * `correlacion_identidad` es el puente con Authentik: la persona pertenece al
 * equipo `ClinicaSalud:<correlacion>` y esa correlacion es la que aparece en el
 * token. Ver App\Services\Auth\ResolutorDeSesion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinicas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->string('slug', 80)->unique();
            $tabla->string('correlacion_identidad', 60)->unique();
            $tabla->string('nombre', 160);
            $tabla->string('razon_social', 160)->nullable();
            $tabla->string('rut', 20)->nullable();
            $tabla->string('direccion', 180)->nullable();
            $tabla->string('telefono', 30)->nullable();
            $tabla->string('correo', 120)->nullable();
            $tabla->string('zona_horaria', 60)->default('America/Santiago');
            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinicas');
    }
};