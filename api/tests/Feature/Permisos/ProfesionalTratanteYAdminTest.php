<?php

declare(strict_types=1);

namespace Tests\Feature\Permisos;

use App\Enums\RolClinico;
use App\Models\Clinica;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El profesional tratante si lee la ficha; otro profesional de la misma
 * clinica, no.
 *
 * Y el administrador de la clinica tampoco: la direccion no es un cargo
 * clinico. Esa es una decision de producto, y esta prueba la fija, para que no
 * sedboque por "era mas comodo" dentro de seis meses.
 */
class ProfesionalTratanteYAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_profesional_tratante_abre_la_ficha(): void
    {
        $clinica = Clinica::factory()->create();

        [$tratante] = $this->crearProfesional($clinica, 'Profesional tratante');
        $paciente = $this->crearPaciente($clinica);
        $this->asignarCuidado($tratante, $paciente);
        $this->crearEvolucion($tratante, $paciente);

        $this->actingAs($tratante)
            ->getJson('/api/v1/fichas/' . $paciente->id)
            ->assertOk()
            ->assertJsonPath('paciente.id', $paciente->id)
            ->assertJsonCount(1, 'evoluciones');
    }

    public function test_un_profesional_que_no_trata_al_paciente_no_abre_la_ficha(): void
    {
        $clinica = Clinica::factory()->create();

        [$tratante] = $this->crearProfesional($clinica, 'Profesional tratante');
        [$otro] = $this->crearProfesional($clinica, 'Otro profesional');

        $paciente = $this->crearPaciente($clinica);
        $this->asignarCuidado($tratante, $paciente);
        $this->crearEvolucion($tratante, $paciente);

        // Misma clinica, mismo rol, distinto paciente: el permiso no depende
        // solo del rol.
        $this->actingAs($otro)
            ->getJson('/api/v1/fichas/' . $paciente->id)
            ->assertNotFound();
    }

    public function test_el_administrador_no_abre_una_ficha_clinica(): void
    {
        $clinica = Clinica::factory()->create();

        [$tratante] = $this->crearProfesional($clinica, 'Profesional tratante');

        $administrador = $this->dentroDe($clinica, fn (): Usuario => Usuario::factory()->create([
            'tenant_id' => $clinica->id,
            'rol' => RolClinico::Administrador,
            'nombre' => 'Direccion de prueba',
        ]));

        $paciente = $this->crearPaciente($clinica);
        $this->asignarCuidado($tratante, $paciente);
        $this->crearEvolucion($tratante, $paciente);

        $this->actingAs($administrador)
            ->getJson('/api/v1/fichas/' . $paciente->id)
            ->assertNotFound();
    }

    public function test_un_usuario_desactivado_no_agenda(): void
    {
        $clinica = Clinica::factory()->create();

        $recepcion = $this->dentroDe($clinica, fn (): Usuario => Usuario::factory()->create([
            'tenant_id' => $clinica->id,
            'rol' => RolClinico::Recepcion,
            'activo' => false,
        ]));

        // 404 y no 403: el rechazo viene de la Policy (`viewAny` devuelve
        // false porque la cuenta esta inactiva), y dentro de `/api/v1` todo
        // rechazo de autorizacion se traduce a 404. Ver bootstrap/app.php.
        $this->actingAs($recepcion)
            ->getJson('/api/v1/pacientes')
            ->assertNotFound();
    }
}