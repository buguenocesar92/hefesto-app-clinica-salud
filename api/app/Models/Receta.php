<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoReceta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Receta o certificado.
 *
 * `contenido` es el texto que se imprime. ClinicaSalud arma el documento
 * imprimible, pero NO lo firma: la firma criptografica la hace DocuSeal, que
 * ya esta en el catalogo de Hefesto, y acá solo queda la referencia
 * (`documento_referencia_externo`) para poder mostrar que existe.
 *
 * La receta no se borra. Se anula. Ver docs/07.
 */
class Receta extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'recetas';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'paciente_id',
        'profesional_id',
        'cita_id',
        'fecha',
        'contenido',
        'estado',
        'emitida_en',
        'documento_referencia_externo',
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
            'fecha' => 'datetime',
            'emitida_en' => 'datetime',
            'estado' => EstadoReceta::class,
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

    /** @return BelongsTo<Profesional, $this> */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }

    /** @return BelongsTo<Cita, $this> */
    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    /**
     * La receta fue firmada fuera y se puede mostrar como referencia.
     */
    public function firmada(): bool
    {
        return $this->documento_referencia_externo !== null;
    }

    public function estaAnulada(): bool
    {
        return $this->estado === EstadoReceta::Anulada;
    }
}