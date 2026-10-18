<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\AccionFicha;
use App\Enums\ResultadoAcceso;
use App\Services\Fichas\BitacoraFichas;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Audita el acceso a la ficha clinica.
 *
 * Se aplica con el alias `ficha.auditada` a las unicas rutas de la API que
 * abren informacion clinica (ver routes/web.php). Es el UNICO punto donde se
 * anota: un solo lugar es una unica garantia.
 *
 * POR QUE EL MIDDLEWARE Y NO EL CONTROLADOR
 * -----------------------------------------
 * El permiso lo aplica la Policy, dentro del controlador. Si el acceso se
 * deniega, la Policy lanza una excepcion y el controlador nunca llega a
 * escribir nada. Como en una ficha clinica los intentos denegados importan
 * tanto como los permitidos, el registro tiene que vivir POR FUERA del
 * controlador: este middleware envuelve la llamada y anota el resultado
 * incluso cuando la peticion falla.
 *
 * Cada ruta de ficha lleva `{paciente}` como primer segmento, asi que el
 * middleware sabe a que ficha se intento acceder sin consultar la base.
 */
class AuditarFicha
{
    /** Rutas de ficha clinica, por nombre de ruta. */
    private const ACCIONES = [
        'api.v1.fichas.show' => AccionFicha::VerHistorial,
        'api.v1.fichas.evoluciones.index' => AccionFicha::VerEvolucion,
        'api.v1.fichas.evoluciones.show' => AccionFicha::VerEvolucion,
        'api.v1.fichas.evoluciones.store' => AccionFicha::CrearEvolucion,
        'api.v1.fichas.recetas.index' => AccionFicha::VerReceta,
        'api.v1.fichas.recetas.show' => AccionFicha::VerReceta,
        'api.v1.fichas.recetas.store' => AccionFicha::CrearReceta,
        'api.v1.fichas.recetas.anular' => AccionFicha::AnularReceta,
        'api.v1.fichas.recetas.imprimir' => AccionFicha::ImprimirReceta,
    ];

    public function __construct(private readonly BitacoraFichas $bitacora) {}

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $accion = $this->resolverAccion($peticion);

        // Si la ruta no esta en el catalogo, no se audita: significa que
        // alguien agrego una ruta de ficha sin pasarla por aqui. Es un error
        // de desarrollo, y conviene que se note en el registro general.
        if ($accion === null) {
            Log::warning('Ruta de ficha clinica sin accion de auditoria asociada.', [
                'ruta' => $peticion->path(),
                'nombre_ruta' => $peticion->route()?->getName(),
            ]);

            return $siguiente($peticion);
        }

        try {
            $respuesta = $siguiente($peticion);
        } catch (Throwable $e) {
            // Un rechazo de Policy (403/404), un registro inexistente o
            // cualquier error: el intento queda anotado igual.
            $this->bitacora->registrar(
                $peticion->user(),
                $this->pacienteId($peticion),
                $accion,
                ResultadoAcceso::Denegado,
                $peticion,
                null,
            );

            throw $e;
        }

        $permitido = $respuesta->getStatusCode() < 400;

        $this->bitacora->registrar(
            $peticion->user(),
            $this->pacienteId($peticion),
            $accion,
            $permitido ? ResultadoAcceso::Permitido : ResultadoAcceso::Denegado,
            $peticion,
            $respuesta->getStatusCode(),
        );

        return $respuesta;
    }

    private function resolverAccion(Request $peticion): ?AccionFicha
    {
        $nombre = $peticion->route()?->getName();

        return $nombre === null ? null : (self::ACCIONES[$nombre] ?? null);
    }

    private function pacienteId(Request $peticion): ?string
    {
        $valor = $peticion->route('paciente');

        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}