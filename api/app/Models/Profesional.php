<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Profesional de la clinica: medico, odontologo, enfermero, matrona.
 *
 * `registro_profesional` es el numero de inscripcion en el registro que
 * corresponda a la profesion. La aplicacion lo guarda y lo muestra; NO lo
 * valida contra ningun registro nacional: eso requiere una fuente de datos
 * externa que no esta conectada. Ver docs/10.
 */
class Profesional extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'profesionales';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'nombre',
        'profesion',
        'registro_profesional',
        'correo',
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
        return $this->hasMany(Cita::class, 'profesional_id');
    }

    /** @return HasMany<Evolucion, $this> */
    public function evoluciones(): HasMany
    {
        return $this->hasMany(Evolucion::class, 'profesional_id');
    }
}