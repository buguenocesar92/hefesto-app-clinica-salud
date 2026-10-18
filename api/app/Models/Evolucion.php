<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evolucion: una entrada de la historia clinica.
 *
 * Esta tabla no se actualiza desde la interfaz una vez creada. Si hay que
 * corregir algo, se escribe una evolucion nueva que diga que corrige a la
 * anterior. Es la practica habitual en historia clinica y evita el problema
 * de "quien cambio esto y por que".
 *
 * `codigo_cie10` es texto libre a proposito: la aplicacion no trae el catalogo
 * CIE-10 porque no esta verificado contra la version que usa el Ministerio de
 * Salud. Validarlo contra la fuente oficial es trabajo pendiente, declarado
 * en docs/10.
 */
class Evolucion extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'evoluciones';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'paciente_id',
        'profesional_id',
        'cita_id',
        'fecha',
        'motivo_consulta',
        'codigo_cie10',
        'diagnostico',
        'tratamiento',
        'indicaciones',
        'observaciones',
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
}