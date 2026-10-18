<?php

declare(strict_types=1);

namespace Tests\Feature\Permisos;

use App\Models\AccesoFicha;
use App\Models\Clinica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * La bitacora de accesos es evidencia: no se modifica ni se borra.
 *
 * Si estas pruebas fallaran, el sistema de auditoria del producto seria
 * decorativo: alguien podria borrar la huella de que abrio una ficha de un
 * paciente.
 */
class BitacoraInmutableTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_registro_de_bitacora_no_se_puede_modificar(): void
    {
        $clinica = Clinica::factory()->create();

        $registro = $this->dentroDe($clinica, fn (): AccesoFicha => AccesoFicha::query()->create([
            'usuario_id' => null,
            'paciente_id' => null,
            'accion' => 'ver_historial',
            'resultado' => 'permitido',
            'metodo_http' => 'GET',
            'ruta' => 'api/v1/fichas/ejemplo',
        ]));

        $this->expectException(LogicException::class);

        $this->dentroDe($clinica, function () use ($registro): void {
            $registro->accion = 'otra_cosa';
            $registro->save();
        });
    }

    public function test_un_registro_de_bitacora_no_se_puede_borrar(): void
    {
        $clinica = Clinica::factory()->create();

        $registro = $this->dentroDe($clinica, fn (): AccesoFicha => AccesoFicha::query()->create([
            'accion' => 'ver_historial',
            'resultado' => 'permitido',
            'metodo_http' => 'GET',
            'ruta' => 'api/v1/fichas/ejemplo',
        ]));

        $this->expectException(LogicException::class);

        $this->dentroDe($clinica, fn () => $registro->delete());
    }
}