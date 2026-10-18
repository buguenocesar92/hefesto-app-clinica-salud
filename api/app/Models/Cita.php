<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoCita;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cita de la agenda.
 *
 * Las horas se guardan en `timestamp with time zone` de PostgreSQL y se
 * presentan en la zona horaria de la clinica. Guardarlas como texto sin zona
 * es la causa clasica de que la agenda se corra una hora en el cambio de hora
 * de verano. Ver docs/02.
 *
 * `fin` se calcula a partir de `inicio` y de la duracion del tipo de atencion:
 * la duracion vive en el tipo de atencion, no en la cita.
 */
class Cita extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'citas';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'paciente_id',
        'profesional_id',
        'sala_id',
        'tipo_atencion_id',
        'inicio',
        'fin',
        'estado',
        'motivo_cancelacion',
        'notas',
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
            'estado' => EstadoCita::class,
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

    /** @return BelongsTo<Sala, $this> */
    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    /** @return BelongsTo<TipoAtencion, $this> */
    public function tipoAtencion(): BelongsTo
    {
        return $this->belongsTo(TipoAtencion::class, 'tipo_atencion_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por_usuario_id');
    }

    /**
     * Duracion efectiva de la cita, en minutos.
     */
    public function duracionMinutos(): int
    {
        return (int) round(($this->fin->getTimestamp() - $this->inicio->getTimestamp()) / 60);
    }

    /**
     * La cita sigue reservando espacio en la agenda.
     */
    public function ocupaAgenda(): bool
    {
        return $this->estado->ocupaAgenda();
    }

    /**
     * Se puede cambiar el estado a algo que no sea final.
     */
    public function estadoEsFinal(): bool
    {
        return $this->estado->esFinal();
    }
}