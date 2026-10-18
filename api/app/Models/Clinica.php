<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * La clinica: el tenant de esta aplicacion.
 *
 * Es la UNICA tabla sin columna `tenant_id`, y por eso la unica sin Row Level
 * Security: cada fila es una clinica completa y se necesita leerla para
 * resolver el contexto (nombre, zona horaria) antes de que exista contexto.
 * Todas las demas tablas cuelgan de aca.
 *
 * Ver docs/03-MULTITENANCY-Y-ROLES.md.
 */
class Clinica extends Model
{
    use HasUuids;

    protected $table = 'clinicas';

    /** La ruta de datos de salud mas delicada de la aplicacion. */
    protected const RUTA_SENSIBLE = 'clinica-salud';

    protected $fillable = [
        'slug',
        'nombre',
        'razon_social',
        'rut',
        'direccion',
        'telefono',
        'correo',
        'zona_horaria',
        'activo',
    ];

    /**
     * Los atributos que se pueden exponer hacia afuera. La lista es explicita
     * a proposito: agregar una columna sensible obliga a decidir si aparece.
     *
     * @var list<string>
     */
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

    /** @return HasMany<Usuario, $this> */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'tenant_id');
    }

    /** @return HasMany<Profesional, $this> */
    public function profesionales(): HasMany
    {
        return $this->hasMany(Profesional::class, 'tenant_id');
    }

    /** @return HasMany<Sala, $this> */
    public function salas(): HasMany
    {
        return $this->hasMany(Sala::class, 'tenant_id');
    }

    /** @return HasMany<Paciente, $this> */
    public function pacientes(): HasMany
    {
        return $this->hasMany(Paciente::class, 'tenant_id');
    }
}