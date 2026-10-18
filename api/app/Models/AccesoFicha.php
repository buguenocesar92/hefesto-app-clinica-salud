<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccionFicha;
use App\Enums\ResultadoAcceso;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Un acceso a la ficha clinica. Solo se anade.
 *
 * Esta tabla es la evidencia de "quien mire que ficha, cuando y desde donde".
 * Por eso el modelo no permite actualizarla ni borrarla: si se pudiera, la
 * bitacora dejaria de ser evidencia en el momento en que mas importa.
 *
 * Dos decisiones que se notan en el esquema (ver migraciones):
 *
 *  - `paciente_id` NO lleva clave foranea a `pacientes`. Si la lleva, borrar
 *    (o volver invisible) una ficha dejaria un hueco en la bitacora, y el
 *    registro tendria que buscarse por nombre, que es dato personal mutable.
 *    Se guarda el identificador y listo: la bitacora no depende de que la
 *    ficha siga existiendo.
 *
 *  - Se escribe por la conexion `auditoria` (ver BitacoraFichas), que sobrevive
 *    a la reversa de la transaccion de la peticion. Si el acceso fue denegado,
 *    la transaccion se revierte: en la misma conexion el registro se perderia.
 */
class AccesoFicha extends Model
{
    use HasUuids;

    protected $table = 'accesos_fichas';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'usuario_id',
        'paciente_id',
        'accion',
        'resultado',
        'metodo_http',
        'ruta',
        'nombre_ruta',
        'direccion_ip',
        'agente_usuario',
        'codigo_respuesta',
    ];

    /** La bitacora no tiene created_at/updated_at: `registrado_en` es la fecha. */
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    /**
     * El modelo se cierra para escritura: solo se permite crear.
     */
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('La bitacora de accesos a ficha clinica no se modifica.');
        });

        static::deleting(function (): never {
            throw new LogicException('La bitacora de accesos a ficha clinica no se borra.');
        });
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function fuePermitido(): bool
    {
        return $this->resultado === ResultadoAcceso::Permitido;
    }

    public function fueDenegado(): bool
    {
        return $this->resultado === ResultadoAcceso::Denegado;
    }

    public function accionComoAccion(): AccionFicha
    {
        return AccionFicha::from($this->accion);
    }
}