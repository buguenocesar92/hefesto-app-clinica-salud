<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Sexo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Paciente de la clinica.
 *
 * Esta tabla es lo que la recepcion SI puede ver: nombre, RUT, contacto,
 * cobertura. Es informacion personal, pero NO es informacion clinica, y la
 * diferencia importa: recepcion necesita saber a quien agenda.
 *
 * Lo que NO esta aca: diagnosticos, evolveciones, recetas. Esas cosas viven en
 * tablas separadas y son la ficha clinica.
 */
class Paciente extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'pacientes';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'rut',
        'nombres',
        'apellidos',
        'fecha_nacimiento',
        'sexo',
        'telefono',
        'correo',
        'direccion',
        'cobertura',
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
            'fecha_nacimiento' => 'date',
            'sexo' => Sexo::class,
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
        return $this->hasMany(Cita::class, 'paciente_id');
    }

    /** @return HasMany<Consentimiento, $this> */
    public function consentimientos(): HasMany
    {
        return $this->hasMany(Consentimiento::class, 'paciente_id');
    }

    /** @return HasMany<Evolucion, $this> */
    public function evoluciones(): HasMany
    {
        return $this->hasMany(Evolucion::class, 'paciente_id');
    }

    /** @return HasMany<Receta, $this> */
    public function recetas(): HasMany
    {
        return $this->hasMany(Receta::class, 'paciente_id');
    }

    /** @return HasMany<AsignacionCuidado, $this> */
    public function equipoDeCuidado(): HasMany
    {
        return $this->hasMany(AsignacionCuidado::class, 'paciente_id');
    }

    /**
     * Nombre completo, como se muestra en la agenda.
     */
    public function nombreCompleto(): string
    {
        return trim("{$this->nombres} {$this->apellidos}");
    }

    /**
     * Edad al dia de hoy. Se calcula, no se guarda: una edad guardada queda
     * mala al dia siguiente.
     */
    public function edad(): ?int
    {
        return $this->fecha_nacimiento?->age;
    }
}