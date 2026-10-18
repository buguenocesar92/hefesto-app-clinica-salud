<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitacora de accesos a la ficha clinica. Solo anade.
 *
 * TRES DECISIONES QUE NO SON OBVIAS
 *
 * 1. SIN CLAVE FORANEA A `pacientes`. La bitacora tiene que sobrevivir a que la
 *    ficha se borre o se vuelva invisible. Si hubiera FK, un borrado en
 *    cascada abriria un hueco justo en el registro que explica el borrado. Se
 *    guarda el identificador y ya: la evidencia no depende de la fila.
 *
 * 2. SIN CLAVE FORANEA A `usuarios`. Mismo motivo, y ademas las cuentas se
 *    desactivan, no se borran, pero el identificador sigue siendo el dato que
 *    importa.
 *
 * 3. `accion` es texto, no un tipo de dominio. Anadir una accion nueva (por
 *    ejemplo "exportar") no puede exigir una migracion en una base con
 *    millones de registros. El catalogo esta en App\Enums\AccionFicha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accesos_fichas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->uuid('usuario_id')->nullable();
            $tabla->uuid('paciente_id')->nullable();
            $tabla->string('accion', 60);
            $tabla->string('resultado', 20);
            $tabla->string('metodo_http', 10);
            $tabla->string('ruta', 200);
            $tabla->string('nombre_ruta', 80)->nullable();
            $tabla->string('direccion_ip', 45)->nullable();
            $tabla->string('agente_usuario', 500)->nullable();
            $tabla->smallInteger('codigo_respuesta')->nullable();
            $tabla->timestampTz('registrado_en')->useCurrent();

            $tabla->foreign('tenant_id')->references('id')->on('clinicas')->cascadeOnDelete();
            $tabla->index(['tenant_id', 'registrado_en']);
            $tabla->index(['tenant_id', 'paciente_id', 'registrado_en']);
            $tabla->index(['tenant_id', 'usuario_id', 'registrado_en']);
            $tabla->index(['tenant_id', 'resultado', 'registrado_en']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $tabla): void {
            $tabla->string('email')->primary();
            $tabla->string('token');
            $tabla->timestampTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('accesos_fichas');
    }
};