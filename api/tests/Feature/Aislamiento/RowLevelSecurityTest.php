<?php

declare(strict_types=1);

namespace Tests\Feature\Aislamiento;

use App\Models\Clinica;
use App\Models\Paciente;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Comprobacion directa de la Row Level Security, sin pasar por la aplicacion.
 *
 * Ejecuta SQL crudo con el contexto puesto a mano. Si estas consultas
 * devolvieran filas de la otra clinica, habria un problema de aislamiento por
 * mas que la aplicacion se portara bien.
 */
class RowLevelSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const TABLAS_PROTEGIDAS = [
        'profesionales',
        'salas',
        'tipos_atencion',
        'tarifas',
        'usuarios',
        'pacientes',
        'consentimientos',
        'citas',
        'bloqueos_agenda',
        'asignaciones_cuidado',
        'evoluciones',
        'recetas',
        'accesos_fichas',
    ];

    public function test_todas_las_tablas_de_negocio_tienen_rls_forzado(): void
    {
        foreach (self::TABLAS_PROTEGIDAS as $tabla) {
            $fila = DB::selectOne(
                'SELECT relrowsecurity AS rls, relforcerowsecurity AS forzado
                 FROM pg_class WHERE relname = ?',
                [$tabla]
            );

            $this->assertNotNull($fila, "La tabla {$tabla} no existe.");

            $this->assertTrue(
                (bool) $fila->rls,
                "La tabla {$tabla} tiene RLS pero no lo tiene habilitado."
            );

            $this->assertTrue(
                (bool) $fila->forzado,
                "La tabla {$tabla} tiene RLS sin FORZAR: las politicas no le aplican al dueno."
            );
        }
    }

    public function test_cada_tabla_protegida_tiene_la_politica_de_aislamiento(): void
    {
        foreach (self::TABLAS_PROTEGIDAS as $tabla) {
            $conteo = DB::selectOne(
                'SELECT COUNT(*) AS total FROM pg_policies
                 WHERE schemaname = current_schema() AND tablename = ? AND policyname = ?',
                [$tabla, 'aislamiento_tenant']
            );

            $this->assertSame(
                1,
                (int) $conteo->total,
                "La tabla {$tabla} no tiene la politica aislamiento_tenant."
            );
        }
    }

    public function test_la_funcion_de_contexto_falla_sin_variable(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::select('SELECT public.app_clinica_id()');
    }

    public function test_la_funcion_de_contexto_devuelve_la_clinica_fijada(): void
    {
        $clinica = Clinica::factory()->create();
        $paciente = $this->dentroDe($clinica, fn (): Paciente => Paciente::factory()->create([
            'tenant_id' => $clinica->id,
        ]));

        $valor = $this->dentroDe($clinica, fn (): string => (string) DB::selectOne(
            'SELECT public.app_clinica_id()::text AS valor'
        )->valor);

        $this->assertSame($clinica->id, $valor);
        $this->assertNotSame('', $paciente->id);
    }

    public function test_el_contexto_desaparece_al_terminar_la_transaccion(): void
    {
        $clinica = Clinica::factory()->create();

        $creado = $this->dentroDe($clinica, fn (): Paciente => Paciente::factory()->create([
            'tenant_id' => $clinica->id,
        ]));

        $this->assertSame($clinica->id, $creado->tenant_id);

        // Fuera de la transaccion no debe quedar variable. Si quedara, el
        // worker de colas podria operar con la clinica de la peticion anterior.
        $this->assertNull(
            app(ContextoTenant::class)->clinicaIdEnConexion(),
            'La variable app.clinica_id sobrevivio a la transaccion.'
        );
    }
}