<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignacion de cuidado: este profesional atiende a este paciente.
 *
 * Es la forma ADMINISTRATIVA de ser tratante, para clinicas que tienen
 * equipos de salud definidos (un equipo de kinesiologia, un programa de
 * Attention a crónicos). Las otras dos formas de ser tratante son tener una
 * cita o haber escrito una evolucion.
 *
 * La fila no se borra cuando termina el episodio de cuidado: se le quita
 * `activo`, para que el historico de quien accedio a la ficha siga siendo
 * explicable.
 */
class AsignacionCuidado extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'asignaciones_cuidado';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'paciente_id',
        'profesional_id',
        'activo',
        'motivo',
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
            'activo' => 'boolean',
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
}