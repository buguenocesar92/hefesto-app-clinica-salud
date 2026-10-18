<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bloqueo de agenda: feriado, almuerzo, reunion, vacaciones.
 *
 * Un bloqueo puede aplicar a un profesional, a una sala o a toda la clinica:
 * si `profesional_id` y `sala_id` son nulos, bloquea a todos. Es la forma de
 * que la clinica deje de exponer la agenda sin borrar citas.
 */
class BloqueoAgenda extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'bloqueos_agenda';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'profesional_id',
        'sala_id',
        'inicio',
        'fin',
        'motivo',
        'creado_por_usuario_id',
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
            'inicio' => 'datetime',
            'fin' => 'datetime',
        ];
    }

    /** @return BelongsTo<Clinica, $this> */
    public function clinica(): BelongsTo
    {
        return $this->belongsTo(Clinica::class, 'tenant_id');
    }

    /** @return BelongsTo<Profesional, $this> */
    public function profesional(): BelongsTo
    {
        return $this->belongsTo(Profesional::class, 'profesional_id');
    }

    /** @return BelongsTo<Sala, $this> */
    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    /**
     * Bloqueo general de la clinica.
     */
    public function esGeneral(): bool
    {
        return $this->profesional_id === null && $this->sala_id === null;
    }
}