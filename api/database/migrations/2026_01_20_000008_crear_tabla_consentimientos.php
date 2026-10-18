<?php

declare(strict_types=1);

use App\Enums\TipoConsentimiento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consentimientos del paciente.
 *
 * `documento_referencia_externo` es la referencia del documento firmado en
 * DocuSeal. ClinicaSalud NO implementa firma criptografica: lo que se guarda
 * aca es el identificador de un documento firmado en otro sistema, no una
 * firma propia. Ver docs/03.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consentimientos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->uuid('tenant_id');
            $tabla->uuid('paciente_id');
            $tabla->string('tipo', 30);
            $tabla->boolean('otorgado');
            $tabla->date('fecha');
            $tabla->string('documento_referencia_externo', 120)->nullable();
            $tabla->uuid('registrado_por_usuario_id')->nullable();
            $tabla->string('notas', 500)->nullable();
            $tabla->timestampsTz();

            $tabla->foreign('tenant_id')->references('id')->on('clinicas')->cascadeOnDelete();
            $tabla->foreign('paciente_id')->references('id')->on('pacientes')->cascadeOnDelete();
            $tabla->foreign('registrado_por_usuario_id')->references('id')->on('usuarios')->nullOnDelete();
            $tabla->index(['tenant_id', 'paciente_id', 'fecha']);

            // La FK de `paciente_id` es CASCADE a proposito: si se elimina la
            // ficha de identificacion del paciente, sus consentimientos dejan
            // de tener a quien pertenecer. (La bitacora de accesos, en cambio,
            // NO tiene FK: ver migracion de `accesos_fichas`.)
        });

        $tipos = array_map(
            static fn (TipoConsentimiento $tipo): string => "'" . $tipo->value . "'",
            TipoConsentimiento::cases()
        );

        DB::statement(
            'ALTER TABLE consentimientos ADD CONSTRAINT consentimientos_tipo_check CHECK (tipo IN (' . implode(', ', $tipos) . '))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('consentimientos');
    }
};