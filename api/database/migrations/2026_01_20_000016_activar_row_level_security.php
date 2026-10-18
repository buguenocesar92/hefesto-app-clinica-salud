<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Row Level Security: el aislamiento entre clinicas lo impone PostgreSQL.
 *
 * MISMO PATRON DEL PORTAL DE AUTOSERVICIO, MISMA JUSTIFICACION
 * ------------------------------------------------------------
 * La justificacion completa esta en
 * apps/portal-autoservicio/docs/03-MULTITENANCY.md. La decision fue, y aqui
 * se repite tal cual: columna `tenant_id` mas politicas de RLS, NO un esquema
 * de base de datos por clinica. El resumen de por que:
 *
 *  - Con un esquema por clinica, los indices y las consultas dejan de
 *    compartirse, el planificador se sobredimensiona y los respaldos se
 *    multiplican.
 *  - Con RLS, el aislamiento no depende de que la aplicacion se acuerde de
 *    filtrar: si una consulta olvida el `where tenant_id`, PostgreSQL igual
 *    devuelve cero filas de las otras clinicas.
 *  - Con RLS, un error de la aplicacion no es una filtracion de datos de
 *    salud: es un error.
 *
 * TRES COSAS QUE ESTA MIGRACION HACE Y QUE HAY QUE ENTENDER
 *
 * 1. `public.app_clinica_id()` LANZA EXCEPCION si no hay contexto.
 *    Si no lo hiciera, una consulta sin `tenant_id` devolveria "cero filas"
 *    en vez de fallar. Falla cerrado: la ausencia de contexto es un error, no
 *    una condicion legitima.
 *
 * 2. `FORCE ROW LEVEL SECURITY` en cada tabla. Sin esto, las politicas NO le
 *    aplican al dueño de la tabla, y Laravel se conecta como el dueño (el
 *    mismo usuario que migra). El RLS de adorno no protege nada.
 *
 * 3. La politica se aplica por `tenant_id`, que es la columna que todas las
 *    tablas de negocio tienen. Sin funcion `SECURITY DEFINER` a proposito: una
 *    funcion definida por el dueño saltaria sus propias politicas.
 */
return new class extends Migration
{
    /**
     * Tablas de negocio, todas con `tenant_id`.
     *
     * @var list<string>
     */
    private const TABLAS = [
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

    public function up(): void
    {
        $this->crearFuncionDeContexto();

        foreach (self::TABLAS as $tabla) {
            $this->proteger($tabla);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLAS) as $tabla) {
            DB::statement('ALTER TABLE ' . $tabla . ' NO FORCE ROW LEVEL SECURITY');
            DB::statement('DROP POLICY IF EXISTS aislamiento_tenant ON ' . $tabla);
        }

        DB::statement('DROP FUNCTION IF EXISTS public.app_clinica_id()');
    }

    /**
     * Funcion que lee el contexto de clinica de la peticion.
     *
     * `current_setting(..., true)` devuelve NULL si la variable no existe (en
     * vez de fallar), que es justamente el caso que hay que detectar.
     */
    private function crearFuncionDeContexto(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.app_clinica_id() RETURNS uuid
            LANGUAGE plpgsql
            STABLE
            AS $$
            DECLARE
                valor text;
            BEGIN
                valor := current_setting('app.clinica_id', true);

                IF valor IS NULL OR valor = '' THEN
                    RAISE EXCEPTION
                        'Falta el contexto de clinica (app.clinica_id). '
                        'Usa App\Services\Tenancy\ContextoTenant::ejecutarAislado().'
                        USING ERRCODE = 'insufficient_privilege';
                END IF;

                RETURN valor::uuid;
            END;
            $$
            SQL);
    }

    private function proteger(string $tabla): void
    {
        DB::statement('ALTER TABLE ' . $tabla . ' ENABLE ROW LEVEL SECURITY');

        // Sin esto, las politicas no aplican al dueno de la tabla.
        DB::statement('ALTER TABLE ' . $tabla . ' FORCE ROW LEVEL SECURITY');

        DB::statement(<<<SQL
            CREATE POLICY aislamiento_tenant ON {$tabla}
                USING (tenant_id = public.app_clinica_id())
                WITH CHECK (tenant_id = public.app_clinica_id())
            SQL);
    }
};