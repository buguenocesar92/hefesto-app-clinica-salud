<?php

declare(strict_types=1);

namespace App\Http\Controllers\FichaClinica;

use App\Enums\EstadoReceta;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FichaClinica\Concerns\ResuelvePacienteDeFicha;
use App\Http\Requests\EmitirRecetaRequest;
use App\Http\Resources\RecetaResource;
use App\Models\Receta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recetas y certificados.
 *
 * Emitir una receta es una accion clinica: solo el tratante. Ver RecetaPolicy.
 *
 * SOBRE LA FIRMA
 * --------------
 * `documento_referencia_externo` es la referencia del documento firmado en
 * DocuSeal. ClinicaSalud arma el texto y el formato imprimible, pero NO
 * firma: implementar firma criptografica seria duplicar lo que DocuSeal ya
 * hace, y con una garantia mas baja. Ver docs/03.
 */
class RecetaController extends Controller
{
    use ResuelvePacienteDeFicha;

    public function index(Request $peticion, string $paciente)
    {
        $modelo = $this->pacienteDeFicha($peticion, $paciente);

        $this->authorize('verFicha', $modelo);

        $recetas = $modelo->recetas()
            ->with('profesional:id,tenant_id,nombre,profesion,registro_profesional')
            ->orderByDesc('fecha')
            ->paginate(25);

        return RecetaResource::collection($recetas);
    }

    public function show(Request $peticion, string $paciente, string $receta)
    {
        $modelo = $this->pacienteDeFicha($peticion, $paciente);

        $registro = $this->recetaDeFicha($modelo->tenant_id, $modelo->id, $receta);

        $this->authorize('view', $registro);

        return new RecetaResource($registro);
    }

    public function store(EmitirRecetaRequest $peticion, string $paciente): JsonResponse
    {
        $modelo = $this->pacienteDeFicha($peticion, $paciente);

        $this->authorize('create', [Receta::class, $modelo]);

        $datos = $peticion->validated();
        $emitir = (bool) ($datos['emitir'] ?? true);

        $receta = Receta::query()->create([
            'tenant_id' => $modelo->tenant_id,
            'paciente_id' => $modelo->id,
            'profesional_id' => $peticion->user()->profesional_id,
            'cita_id' => $datos['cita_id'] ?? null,
            'fecha' => $datos['fecha'] ?? now(),
            'contenido' => $datos['contenido'],
            'estado' => $emitir ? EstadoReceta::Emitida : EstadoReceta::Borrador,
            'emitida_en' => $emitir ? now() : null,
            'documento_referencia_externo' => $datos['documento_referencia_externo'] ?? null,
        ]);

        $receta->load('profesional');

        return (new RecetaResource($receta))->response()->setStatusCode(201);
    }

    /**
     * Anular una receta. La receta no se borra.
     *
     * Ruta aparte (POST, no PATCH) porque anular no es editar: es un cambio de
     * estado con consecuencia clinica.
     */
    public function anular(Request $peticion, string $paciente, string $receta): JsonResponse
    {
        $modelo = $this->pacienteDeFicha($peticion, $paciente);

        $registro = $this->recetaDeFicha($modelo->tenant_id, $modelo->id, $receta);

        $this->authorize('anular', $registro);

        $registro->estado = EstadoReceta::Anulada;
        $registro->save();

        return response()->json([
            'mensaje' => 'Receta anulada.',
            'id' => $registro->id,
            'estado' => $registro->estado->value,
        ]);
    }

    private function recetaDeFicha(string $clinicaId, string $pacienteId, string $recetaId): Receta
    {
        $registro = Receta::query()
            ->where('tenant_id', $clinicaId)
            ->where('paciente_id', $pacienteId)
            ->whereKey($recetaId)
            ->with('profesional')
            ->first();

        if ($registro === null) {
            abort(404, 'No encontrado.');
        }

        return $registro;
    }
}