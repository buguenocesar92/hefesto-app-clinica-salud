<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sala de atencion.
 *
 * Una sala es un recurso que se agenda: dos citas no pueden ocupar la misma
 * sala al mismo tiempo. El profesional tambien lo es, y la misma regla aplica
 * para el. Ver App\Services\Agenda\VerificadorDeDisponibilidad.
 */
class Sala extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'salas';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'nombre',
        'piso',
        'activa',
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
            'activa' => 'boolean',
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
        return $this->hasMany(Cita::class, 'sala_id');
    }
}