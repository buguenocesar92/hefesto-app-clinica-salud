<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesiones.
 *
 * NO lleva `tenant_id` ni Row Level Security, a proposito: la sesion se
 * consulta ANTES de saber de que clinica se trata (por eso existe
 * ResolverTenant, que va antes que `auth`). Si esta tabla estuviera protegida,
 * nadie podria iniciar sesion.
 *
 * Con el controlador `database` la sesion vive en PostgreSQL y sobrevive a un
 * reinicio del contenedor. Con Redis no habria que hacer nada: la sesion
 * tampoco esta ahi. La tabla se crea igual para que cambiar de controlador sea
 * una linea de configuracion, no un trabajo de infraestructura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $tabla): void {
            $tabla->string('id')->primary();
            $tabla->uuid('user_id')->nullable()->index();
            $tabla->string('ip_address', 45)->nullable();
            $tabla->text('user_agent')->nullable();
            $tabla->text('payload');
            $tabla->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $tabla): void {
            $tabla->string('key')->primary();
            $tabla->mediumText('value');
            $tabla->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $tabla): void {
            $tabla->string('key')->primary();
            $tabla->string('owner');
            $tabla->integer('expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
    }
};