<?php

declare(strict_types=1);

namespace Tests\Feature\Aislamiento;

use App\Models\Clinica;
use App\Models\Evolucion;
use App\Models\Paciente;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dos clinicas en la MISMA instalacion no pueden verse.
 *
 * Esta es la suite que mas importa del proyecto, y la que mas conviene correr
 * primero. No prueba la aplicacion: prueba que la base de datos se niegue.
 *
 * CUBRE
 *   1. Una consulta con el contexto de la clinica A no devuelve filas de la B.
 *   2. Sin contexto, cualquier consulta a una tabla protegida falla.
 *   3. No se puede escribir una fila declarando una clinica que no es la del
 *      contexto (`WITH CHECK`).
 *   4. Por la API, una peticion de la clinica A devuelve 404 al pedir un
 *      paciente de la B.
 */
class AislamientoEntreClinicasTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_clinica_no_ve_pacientes_de_otra(): void
    {
        $clinicaA = Clinica::factory()->create(['nombre' => 'Clinica A']);
        $clinicaB = Clinica::factory()->create(['nombre' => 'Clinica B']);

        $usuarioA = $this->dentroDe($clinicaA, fn (): Usuario => Usuario::factory()->create([
            'tenant_id' => $clinicaA->id,
        ]));

        $pacienteB = $this->dentroDe($clinicaB, fn (): Paciente => Paciente::factory()->create([
            'tenant_id' => $clinicaB->id,
            'nombres' => 'Paciente',
            'apellidos' => 'DeLaClinicaB',
        ]));

        // Con el contexto de A, la fila de B no existe a ojos de la consulta.
        $vistos = $this->dentroDe($clinicaA, fn (): array => Paciente::query()->pluck('id')->all());

        $this->assertNotContains($pacienteB->id, $vistos);

        $encontrado = $this->dentroDe($clinicaA, fn (): ?Paciente => Paciente::query()->find($pacienteB->id));

        $this->assertNull($encontrado, 'La RLS deberia ocultar el paciente de la otra clinica.');
    }

    public function test_una_clinica_no_ve_historia_clinica_de_otra(): void
    {
        $clinicaA = Clinica::factory()->create();
        $clinicaB = Clinica::factory()->create();

        [$usuarioB, $profesionalB] = $this->crearProfesional($clinicaB, 'Profesional de B');
        $pacienteB = $this->crearPaciente($clinicaB);
        $this->asignarCuidado($usuarioB, $pacienteB);
        $this->crearEvolucion($usuarioB, $pacienteB);

        $evolucionesVistasPorA = $this->dentroDe($clinicaA, fn (): array => Evolucion::query()->pluck('id')->all());

        $this->assertSame([], $evolucionesVistasPorA, 'A no deberia ver ninguna evolucion ajena.');

        // Y al reves: el profesional de B si ve su propia historia clinica.
        // El filtro no es "nadie ve nada", es "cada uno ve lo suyo".
        $evolucionesVistasPorB = $this->dentroDe($clinicaB, fn (): array => Evolucion::query()->count());

        $this->assertSame(1, $evolucionesVistasPorB);
    }

    public function test_sin_contexto_la_consulta_falla_y_no_devuelve_filas(): void
    {
        $clinica = Clinica::factory()->create();

        $this->dentroDe($clinica, fn (): Paciente => Paciente::factory()->create([
            'tenant_id' => $clinica->id,
        ]));

        // Sin `app.clinica_id`, `public.app_clinica_id()` lanza excepcion. El
        // aislamiento falla cerrado: una consulta sin contexto no devuelve
        // "cero filas y ya", falla.
        $this->expectException(\Illuminate\Database\QueryException::class);

        Paciente::query()->count();
    }

    public function test_no_se_puede_escribir_una_fila_de_otra_clinica(): void
    {
        $clinicaA = Clinica::factory()->create();
        $clinicaB = Clinica::factory()->create();

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Se esta escribiendo desde el contexto de A, declarando que la fila es
        // de B. La politica `WITH CHECK` lo prohibe.
        $this->dentroDe($clinicaA, fn (): Paciente => Paciente::query()->create([
            'tenant_id' => $clinicaB->id,
            'rut' => '11.111.111-1',
            'nombres' => 'Intruso',
            'apellidos' => 'DeOtraClinica',
            'fecha_nacimiento' => '1990-01-01',
        ]));
    }

    public function test_por_la_api_un_paciente_de_otra_clinica_responde_404(): void
    {
        $clinicaA = Clinica::factory()->create();
        $clinicaB = Clinica::factory()->create();

        $usuarioA = $this->dentroDe($clinicaA, fn (): Usuario => Usuario::factory()->create([
            'tenant_id' => $clinicaA->id,
        ]));

        $pacienteB = $this->crearPaciente($clinicaB);

        $respuesta = $this->actingAs($usuarioA)
            ->getJson('/api/v1/pacientes/' . $pacienteB->id);

        $respuesta->assertNotFound();
    }
}