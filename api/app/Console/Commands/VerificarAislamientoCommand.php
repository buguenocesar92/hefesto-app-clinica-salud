<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Clinica;
use App\Models\Paciente;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Comprueba, desde la consola, que la RLS esta puesta y funciona.
 *
 * Sirve para lo mismo que la suite `Aislamiento`, pero en el servidor: antes
 * de recibir pacientes reales, o despues de tocar una migracion.
 *
 *     php artisan clinica:verificar-aislamiento
 *
 * QUE COMPRUEBA, EN ORDEN
 * -----------------------
 *   1. Que el rol con el que conecta la aplicacion NO sea superusuario ni
 *      tenga BYPASSRLS. Va primero, y antes de escribir nada.
 *   2. Que las trece tablas de negocio tengan RLS ACTIVADO y FORZADO.
 *   3. Que una clinica no vea filas de otra, ni por clave primaria.
 *   4. Que una consulta SIN contexto falle, en vez de devolver cero filas.
 *
 * POR QUE EL ROL VA PRIMERO
 * ------------------------
 * Con un rol superusuario, o con BYPASSRLS, PostgreSQL ignora todas las
 * politicas: `FORCE ROW LEVEL SECURITY` sujeta al dueno de la tabla, pero no al
 * superusuario. Las demas comprobaciones seguirian dando verde, porque todas
 * se ejecutan con ese mismo rol. Es el fallo mas grave y el mas silencioso, y
 * por eso se comprueba solo, directo contra `pg_roles`.
 *
 * NO es una prueba unitaria: escribe filas de prueba y las borra al final.
 * No debe correrse contra una base con datos reales.
 */
class VerificarAislamientoCommand extends Command
{
    protected $signature = 'clinica:verificar-aislamiento
        {--clinica : Correlacion de una clinica existente para usar en la prueba. Si no se indica, se crea una clinica temporal.}';

    protected $description = 'Comprueba que el aislamiento entre clinicas funciona a nivel de base de datos.';

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

    public function handle(ContextoTenant $contexto): int
    {
        $fallos = 0;

        // El rol va PRIMERO, antes de mirar las tablas y, sobre todo, antes de
        // escribir las filas temporales. Con un rol que salta RLS, esas filas
        // se escribirían en una base sin aislamiento y el resto de las
        // comprobaciones pasarían en verde sin estar mirando nada.
        if (! $this->revisarRol()) {
            return self::FAILURE;
        }

        $this->components->info('Revisando que las tablas de negocio tengan RLS forzado.');

        foreach (self::TABLAS_PROTEGIDAS as $tabla) {
            $fila = DB::selectOne(
                'SELECT relrowsecurity AS rls, relforcerowsecurity AS forzado FROM pg_class WHERE relname = ?',
                [$tabla]
            );

            if ($fila === null) {
                $this->components->error("La tabla {$tabla} no existe.");
                $fallos++;

                continue;
            }

            if (! $fila->rls || ! $fila->forzado) {
                $this->components->error("La tabla {$tabla} no tiene RLS forzado.");
                $fallos++;
            }
        }

        if ($fallos > 0) {
            $this->components->error('La instalacion NO tiene el aislamiento activo. No sigas.');

            return self::FAILURE;
        }

        $this->components->info('Las tablas tienen RLS forzado.');

        return $this->comprobarLecturaCruzada($contexto);
    }

    /**
     * QUE EL ROL CONECTADO NO SE SALTE LAS POLÍTICAS
     * -----------------------------------------------
     * Consulta `pg_roles` directamente por el nombre del rol y NO depende de
     * que la base tenga datos. Antes el comando no miraba el rol en absoluto:
     * lo detectaba solo de forma indirecta, por la lectura cruzada entre dos
     * clínicas, y esa lectura depende de que las filas temporales se escriban
     * y se lean, no de los atributos del rol.
     *
     * Es el fallo más grave y el más silencioso: un superusuario, o un rol con
     * `BYPASSRLS`, ignora todas las políticas de Row Level Security, y
     * `FORCE ROW LEVEL SECURITY` sujeta al dueño de la tabla pero **no** al
     * superusuario. Con ese rol, una clínica vería las fichas de los pacientes
     * de las demás, sin error y sin aviso.
     */
    private function revisarRol(): bool
    {
        try {
            $rol = DB::selectOne(
                'SELECT rolname AS nombre, rolsuper AS superusuario, rolbypassrls AS bypass
                 FROM pg_roles
                 WHERE rolname = current_user'
            );
        } catch (Throwable $e) {
            $this->components->error('No se pudo leer el rol de la conexión: ' . $e->getMessage());
            $this->components->error('Sin ese dato no se puede afirmar nada sobre el aislamiento.');

            return false;
        }

        if ($rol === null) {
            $this->components->error('No se pudo determinar el rol de la conexión.');

            return false;
        }

        if ($rol->superusuario) {
            $this->components->error("El rol \"{$rol->nombre}\" ES SUPERUSUARIO.");
            $this->components->error('Un superusuario ignora TODAS las políticas de Row Level Security.');
            $this->components->error('`FORCE ROW LEVEL SECURITY` sujeta al dueño de la tabla, pero NO al');
            $this->components->error('superusuario: con este rol el aislamiento entre clínicas no existe.');
            $this->components->error('Qué hacer: `POSTGRES_USER` debe ser el rol de arranque');
            $this->components->error('`clinica_salud_admin`, que la aplicación nunca usa, y el rol de la');
            $this->components->error('aplicación tiene que estar creado NOSUPERUSER NOBYPASSRLS en');
            $this->components->error('`deploy/postgres/inicializar-rol.sh`.');
            $this->components->error('Patrón de referencia: apps/almacen/deploy/postgres/inicializar-rol.sh');

            return false;
        }

        if ($rol->bypass) {
            $this->components->error("El rol \"{$rol->nombre}\" tiene BYPASSRLS.");
            $this->components->error('Con BYPASSRLS, PostgreSQL ignora TODAS las políticas de Row Level Security,');
            $this->components->error('aunque las tablas tengan `FORCE ROW LEVEL SECURITY` puesto.');
            $this->components->error('Qué hacer: el mismo de arriba. `POSTGRES_USER` debe ser el rol de');
            $this->components->error('arranque `clinica_salud_admin`, que la aplicación nunca usa, y el rol de');
            $this->components->error('la aplicación tiene que estar creado NOSUPERUSER NOBYPASSRLS en');
            $this->components->error('`deploy/postgres/inicializar-rol.sh`.');
            $this->components->error('Patrón de referencia: apps/almacen/deploy/postgres/inicializar-rol.sh');

            return false;
        }

        $this->components->info("El rol \"{$rol->nombre}\" no es superusuario ni tiene BYPASSRLS.");

        return true;
    }

    private function comprobarLecturaCruzada(ContextoTenant $contexto): int
    {
        $clinicaA = $this->obtenerOCrearClinica('verificacion-a');
        $clinicaB = $this->obtenerOCrearClinica('verificacion-b');

        $pacienteB = $contexto->ejecutarAislado($clinicaB->id, fn (): Paciente => Paciente::query()->create([
            'tenant_id' => $clinicaB->id,
            'rut' => '00.000.001-1',
            'nombres' => 'Paciente',
            'apellidos' => 'DeVerificacion',
            'fecha_nacimiento' => '1990-01-01',
        ]));

        $vistos = $contexto->ejecutarAislado($clinicaA->id, fn (): int => Paciente::query()
            ->whereKey($pacienteB->id)
            ->count());

        try {
            if ($vistos !== 0) {
                $this->components->error('La clinica A alcanzo a ver un paciente de la clinica B.');
                $this->components->error('El aislamiento NO funciona. Revise FORCE ROW LEVEL SECURITY.');

                return self::FAILURE;
            }

            $this->components->info('A no ve pacientes de B. Correcto.');
        } finally {
            $contexto->ejecutarAislado($clinicaB->id, fn () => $pacienteB->delete());
        }

        try {
            Paciente::query()->count();

            $this->components->error('Una consulta sin contexto devolvio filas en vez de fallar.');
            $this->components->error('La funcion public.app_clinica_id() deberia lanzar excepcion.');

            return self::FAILURE;
        } catch (Throwable) {
            $this->components->info('Sin contexto, la consulta falla. El aislamiento falla cerrado.');
        }

        $this->components->info('Aislamiento verificado.');

        return self::SUCCESS;
    }

    private function obtenerOCrearClinica(string $sufijo): Clinica
    {
        $correlacion = 'clinica-salud-' . $sufijo;

        return Clinica::query()->firstOrCreate(
            ['correlacion_identidad' => $correlacion],
            [
                'slug' => $correlacion,
                'nombre' => 'Temporal de verificacion (' . $sufijo . ')',
                'zona_horaria' => 'America/Santiago',
                'activo' => true,
            ]
        );
    }
}