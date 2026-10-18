<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Concepto del tarifario de la clinica.
 *
 * El precio se guarda en pesos chilenos como entero. No se usa decimal para
 * dinero a proposito: un float introduce errores de redondeo que en un cobro
 * se notan. Cuando la clinica facture de verdad, el valor debe venir de la
 * integracion con el proveedor de facturacion que ya existe en Hefesto, no de
 * esta tabla.
 */
class Tarifa extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'tarifas';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'tipo_atencion_id',
        'concepto',
        'valor',
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
            'valor' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Clinica, $this> */
    public function clinica(): BelongsTo
    {
        return $this->belongsTo(Clinica::class, 'tenant_id');
    }

    /** @return BelongsTo<TipoAtencion, $this> */
    public function tipoAtencion(): BelongsTo
    {
        return $this->belongsTo(TipoAtencion::class, 'tipo_atencion_id');
    }
}