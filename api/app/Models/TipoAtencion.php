<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipo de atencion: "consulta nueva", "control", "procedimiento".
 *
 * `duracion_minutos` es lo que hace util la agenda: al agendar, la cita toma
 * su duracion del tipo de atencion y no de lo que la persona escriba a mano.
 */
class TipoAtencion extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'tipos_atencion';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'nombre',
        'duracion_minutos',
        'color',
        'activo',
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
            'duracion_minutos' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Clinica, $this> */
    public function clinica(): BelongsTo
    {
        return $this->belongsTo(Clinica::class, 'tenant_id');
    }

    /** @return HasMany<Cita, $this> */
    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'tipo_atencion_id');
    }

    /** @return HasMany<Tarifa, $this> */
    public function tarifas(): HasMany
    {
        return $this->hasMany(Tarifa::class, 'tipo_atencion_id');
    }
}