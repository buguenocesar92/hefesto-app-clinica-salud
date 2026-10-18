<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoConsentimiento;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Consentimiento entregado por el paciente.
 *
 * `documento_referencia_externo` guarda el identificador del documento
 * firmado en DocuSeal, que es quien firma de verdad. ClinicaSalud NO
 * implementa firma criptografica: duplicar DocuSeal seria un error, no una
 * feature. Ver docs/03.
 *
 * Si el paciente rechazo el consentimiento queda con `otorgado = false` y la
 * fecha. Borrar ese registro perderia la evidencia de que se le informed.
 */
class Consentimiento extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'consentimientos';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'paciente_id',
        'tipo',
        'otorgado',
        'fecha',
        'documento_referencia_externo',
        'registrado_por_usuario_id',
        'notas',
    ];

    /** @var list<string> */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoConsentimiento::class,
            'otorgado' => 'boolean',
            'fecha' => 'date',
        ];
    }

    /** @return BelongsTo<Clinica, $this> */
    public function clinica(): BelongsTo
    {
        return $this->belongsTo(Clinica::class, 'tenant_id');
    }

    /** @return BelongsTo<Paciente, $this> */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por_usuario_id');
    }

    /**
     * El consentimiento esta firmado fuera (en DocuSeal) y con referencia.
     */
    public function firmado(): bool
    {
        return $this->documento_referencia_externo !== null;
    }
}