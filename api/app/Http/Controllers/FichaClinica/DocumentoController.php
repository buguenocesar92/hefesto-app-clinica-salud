<?php

declare(strict_types=1);

namespace App\Http\Controllers\FichaClinica;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FichaClinica\Concerns\ResuelvePacienteDeFicha;
use App\Models\Clinica;
use App\Models\Paciente;
use App\Models\Receta;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Receta en formato imprimible.
 *
 * Devuelve HTML de una pagina con estilos de impresión, no un PDF: generar el
 * PDF es trabajo del navegador (`window.print()`), y asi no hay dos
 *attesgos de la misma receta que puedan diferir.
 *
 * Esta ruta TAMBIEN queda auditada: imprimir una receta es una forma de sacar
 * informacion clinica de la institucion. Va con el middleware
 * `ficha.auditada` y con la accion `ImprimirReceta`. Ver routes/web.php.
 *
 * La autorizacion es la misma que la de ver la receta: solo el tratante.
 */
class DocumentoController extends Controller
{
    use ResuelvePacienteDeFicha;

    public function receta(Request $peticion, string $paciente, string $receta): Response
    {
        $modelo = $this->pacienteDeFicha($peticion, $paciente);

        $registro = Receta::query()
            ->where('tenant_id', $modelo->tenant_id)
            ->where('paciente_id', $modelo->id)
            ->whereKey($receta)
            ->with('profesional')
            ->first();

        if ($registro === null) {
            abort(404, 'No encontrado.');
        }

        $this->authorize('view', $registro);

        $clinica = $modelo->clinica;

        $html = $this->render($modelo, $registro, $clinica);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            // Sin cache: una receta en la cache del navegador o de un proxy
            // intermedio es una receta fuera de la institucion.
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ]);
    }

    private function render(Paciente $paciente, Receta $receta, ?Clinica $clinica): string
    {
        $contenido = nl2br(e($receta->contenido));
        $anulada = $receta->estaAnulada();

        $estado = $anulada
            ? '<p style="margin:0 0 12px;color:#b91c1c;font-weight:700;">RECETA ANULADA</p>'
            : '';

        $sinFirma = $receta->firmada()
            ? ''
            : '<p style="margin:0 0 12px;color:#92400e;font-weight:700;">Documento sin firma digital: la firma se realiza en DocuSeal.</p>';

        return <<<HTML
            <!doctype html>
            <html lang="es-CL">
            <head>
                <meta charset="utf-8">
                <title>Receta {$paciente->nombreCompleto()}</title>
                <style>
                    body { font-family: Georgia, 'Times New Roman', serif; color:#111; margin:32px; }
                    h1 { font-size: 20px; margin:0 0 4px; }
                    .datos { font-size: 14px; margin-bottom:20px; }
                    .receta { font-size: 15px; line-height:1.5; }
                    .firma { margin-top:48px; font-size: 13px; }
                    .pie { margin-top:28px; font-size: 11px; color:#555; border-top:1px solid #ddd; padding-top:8px; }
                    @media print { body { margin:0; } .no-imprimir { display:none; } }
                </style>
            </head>
            <body>
                {$estado}
                {$sinFirma}
                <h1>{$this->escapar($clinica?->nombre ?? 'Clinica')}</h1>
                <p class="datos">{$this->escapar($clinica?->direccion ?? '')}</p>
                <hr>
                <p class="datos">
                    <strong>Paciente:</strong> {$this->escapar($paciente->nombreCompleto())}<br>
                    <strong>RUT:</strong> {$this->escapar($paciente->rut)}<br>
                    <strong>Fecha:</strong> {$receta->fecha->format('d-m-Y')}
                </p>
                <div class="receta">{$contenido}</div>
                <p class="firma">
                    {$this->escapar($receta->profesional?->nombre ?? '')}<br>
                    {$this->escapar($receta->profesional?->profesion ?? '')}
                    {$this->escapar($receta->profesional?->registro_profesional ?? '')}
                </p>
                <p class="pie">
                    Documento generado por ClinicaSalud. Referencia interna: {$receta->id}.
                </p>
            </body>
            </html>
            HTML;
    }

    private function escapar(?string $valor): string
    {
        return e($valor ?? '');
    }
}