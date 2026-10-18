<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RolClinico;
use App\Services\Permisos\CapacidadesDeUsuario;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Persona que entra a la aplicacion.
 *
 * No tiene contrasena: la identidad la emite Authentik. El correo es la
 * llave de enlace (ver App\Services\Auth\ProveedorOidcAuthentik).
 *
 * Dos columnas deciden casi todo lo que puede hacer:
 *  - `rol`: recepcion, profesional o administrador.
 *  - `profesional_id`: a quien ficha clinica representa. Sin esto, un
 *    profesional no puede leer fichas ni aunque el rol lo permita.
 */
class Usuario extends Authenticatable
{
    use CapacidadesDeUsuario;
    use HasFactory;
    use HasUuids;

    protected $table = 'usuarios';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'nombre',
        'correo',
        'rol',
        'profesional_id',
        'activo',
        'ultimo_ingreso_en',
    ];

    /**
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
            'rol' => RolClinico::class,
            'activo' => 'boolean',
            'ultimo_ingreso_en' => 'datetime',
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

    /**
     * El identificador que aparece en la bitacora y en las respuestas JSON.
     */
    public function nombreVisible(): string
    {
        return $this->nombre;
    }
}