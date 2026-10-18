<?php

declare(strict_types=1);

namespace Tests\Feature\Permisos;

use App\Enums\RolClinico;
use App\Models\AccesoFicha;
use App\Models\Evolucion;
use App\Models\Clinica;
use App\Models\Paciente;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recepcion agenda, pero NO lee historia clinica.
 *
 * Esta es la segunda prueba que mas importa del proyecto, y la que define el
 * producto: el mismo rol que puede crear y mover citas no puede abrir una
 * ficha. Y no por configuracion: no hay bandera que se pueda cambiar desde la
 * aplicacion para darle acceso.
 *
 * Tambien comprueba que el intento DENEGADO queda en la bitacora, que es lo
 * que permite después detectar que alguien esta probando el sistema.
 */
class RecepcionNoLeeFichaClinicaTest extends TestCase
{
    use RefreshDatabase;

    private Clinica $clinica;

    private Usuario $recepcion;

    private Usuario $profesional;

    private Paciente $paciente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinica = Clinica::factory()->create();

        $this->recepcion = $this->dentroDe($this->clinica, fn (): Usuario => Usuario::factory()->create([
            'tenant_id' => $this->clinica->id,
            'rol' => RolClinico::Recepcion,
            'nombre' => 'Recepcion de prueba',
        ]));

        [$this->profesional] = $this->crearProfesional($this->clinica, 'Profesional tratante');

        $this->paciente = $this->crearPaciente($this->clinica);
        $this->asignarCuidado($this->profesional, $this->paciente);
        $this->crearEvolucion($this->profesional, $this->paciente);
    }

    public function test_recepcion_no_abre_la_historia_clinica(): void
    {
        $respuesta = $this->actingAs($this->recepcion)
            ->getJson('/api/v1/fichas/' . $this->paciente->id);

        // 404 y no 403: responder "existe pero no puedes" ya confirma que el
        // paciente existe.
        $respuesta->assertNotFound();

        $cuerpo = $respuesta->json();
        $this->assertStringNotContainsStringIgnoringCase(
            'Diagnostico de prueba',
            (string) json_encode($cuerpo, JSON_UNESCAPED_UNICODE)
        );
    }

    public function test_recepcion_no_abre_las_evoluciones(): void
    {
        $this->actingAs($this->recepcion)
            ->getJson('/api/v1/fichas/' . $this->paciente->id . '/evoluciones')
            ->assertNotFound();
    }

    public function test_recepcion_no_abre_las_recetas(): void
    {
        $this->actingAs($this->recepcion)
            ->getJson('/api/v1/fichas/' . $this->paciente->id . '/recetas')
            ->assertNotFound();
    }

    public function test_recepcion_no_puede_registrar_una_evolucion(): void
    {
        $this->actingAs($this->recepcion)
            ->postJson('/api/v1/fichas/' . $this->paciente->id . '/evoluciones', [
                'motivo_consulta' => 'Intento fuera de rol',
                'diagnostico' => 'Diagnostico inventado',
                'tratamiento' => 'Tratamiento inventado',
            ])
            ->assertNotFound();

        $this->assertSame(
            0,
            $this->dentroDe($this->clinica, fn (): int => Evolucion::query()->count()),
            'No deberia haberse escrito ninguna evolucion nueva.'
        );
    }

    public function test_recepcion_si_puede_agendar_y_ver_pacientes(): void
    {
        // Esto es lo que recepcion PUEDE hacer. Si esta prueba falla, el
        // producto quedo inservible para el mostrador.
        $this->actingAs($this->recepcion)
            ->getJson('/api/v1/pacientes')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_recepcion_puede_ver_el_catalogo_que_necesita_para_agendar(): void
    {
        // Agendar exige elegir profesional, sala y tipo de atencion. Si el
        // catalogo quedara restringido al administrador, la recepcion no
        // podria agendar nada: el mostrador quedaria inutilizable.
        $this->actingAs($this->recepcion)
            ->getJson('/api/v1/admin/profesionales')
            ->assertOk();

        $this->actingAs($this->recepcion)
            ->getJson('/api/v1/admin/salas')
            ->assertOk();

        $this->actingAs($this->recepcion)
            ->getJson('/api/v1/admin/tipos-atencion')
            ->assertOk();
    }

    public function test_recepcion_no_administra_el_catalogo(): void
    {
        // Ver el catalogo no es administerlo: crear un profesional sigue
        // siendo solo del administrador.
        //
        // La respuesta es 404 y no 403 porque el rechazo viene de la Policy, y
        // el bootstrap traduce todo rechazo de autorizacion dentro de
        // `/api/v1` a 404. Ver bootstrap/app.php.
        $this->actingAs($this->recepcion)
            ->postJson('/api/v1/admin/profesionales', [
                'nombre' => 'Profesional inventado',
                'profesion' => 'Medico general',
            ])
            ->assertNotFound();
    }

    public function test_recepcion_no_entra_a_la_bitacora(): void
    {
        // Aqui la respuesta es 403: el corte lo hace el middleware `admin`,
        // que responde 403 y no pasa por la traduccion de la Policy.
        $this->actingAs($this->recepcion)
            ->getJson('/api/v1/admin/auditoria')
            ->assertForbidden();
    }

    public function test_el_intento_denegado_queda_en_la_bitacora(): void
    {
        $this->actingAs($this->recepcion)
            ->getJson('/api/v1/fichas/' . $this->paciente->id)
            ->assertNotFound();

        $registros = $this->dentroDe($this->clinica, fn (): array => AccesoFicha::query()
            ->where('paciente_id', $this->paciente->id)
            ->get()
            ->all());

        $this->assertCount(1, $registros, 'Debio quedar un registro por el intento.');

        $registro = $registros[0];

        $this->assertSame('denegado', $registro->resultado);
        $this->assertSame($this->recepcion->id, $registro->usuario_id);
        $this->assertSame('ver_historial', $registro->accion);
        $this->assertNotNull($registro->registrado_en);
    }

    public function test_el_acceso_permitido_tambien_queda_en_la_bitacora(): void
    {
        $this->actingAs($this->profesional)
            ->getJson('/api/v1/fichas/' . $this->paciente->id)
            ->assertOk()
            ->assertJsonCount(1, 'evoluciones');

        $registro = $this->dentroDe($this->clinica, fn (): AccesoFicha => AccesoFicha::query()
            ->where('paciente_id', $this->paciente->id)
            ->where('resultado', 'permitido')
            ->first());

        $this->assertNotNull($registro);
        $this->assertSame($this->profesional->id, $registro->usuario_id);
    }
}