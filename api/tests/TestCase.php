<?php

declare(strict_types=1);

namespace Tests;

use App\Enums\RolClinico;
use App\Models\AsignacionCuidado;
use App\Models\Clinica;
use App\Models\Evolucion;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Usuario;
use App\Services\Tenancy\ContextoTenant;
use Closure;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base de las pruebas de ClinicaSalud.
 *
 * POR QUE TODAS LAS PRUEBAS NECESITAN `dentroDe`
 * ----------------------------------------------
 * Las tablas de negocio tienen Row Level Security FORZADO, para que las
 * politicas apliquen tambien al dueno de la tabla (que es el usuario con el
 * que se conecta Laravel). Consecuencia: si una prueba crea filas sin declarar
 * la clinica a la que pertenecen, la base las rechaza.
 *
 * Eso no es un estorbo para probar, es parte de lo que se prueba. `dentroDe`
 * es el mismo camino que usa la peticion real: una transaccion con el contexto
 * fijado.
 *
 * PostgreSQL y no SQLite: en SQLite estas pruebas no comprobarian nada, porque
 * no hay RLS. Ver phpunit.xml.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Ejecuta una operacion dentro del contexto de una clinica.
     *
     * @template T
     *
     * @param  Closure(): T  $operacion
     * @return T
     */
    protected function dentroDe(Usuario|Clinica $contexto, Closure $operacion): mixed
    {
        $clinicaId = $contexto instanceof Usuario
            ? (string) $contexto->tenant_id
            : (string) $contexto->id;

        return app(ContextoTenant::class)->ejecutarAislado($clinicaId, $operacion);
    }

    /**
     * Crea un profesional y su usuario, listo para tratar pacientes.
     */
    protected function crearProfesional(Usuario|Clinica $contexto, string $nombre = 'Profesional de prueba'): array
    {
        $clinicaId = $contexto instanceof Usuario ? (string) $contexto->tenant_id : (string) $contexto->id;

        return $this->dentroDe($contexto, function () use ($clinicaId, $nombre): array {
            $profesional = Profesional::factory()->create([
                'tenant_id' => $clinicaId,
                'nombre' => $nombre,
            ]);

            $usuario = Usuario::factory()->create([
                'tenant_id' => $clinicaId,
                'nombre' => $nombre,
                'rol' => RolClinico::Profesional,
                'profesional_id' => $profesional->id,
            ]);

            return [$usuario, $profesional];
        });
    }

    /**
     * Crea un paciente.
     */
    protected function crearPaciente(Usuario|Clinica $contexto): Paciente
    {
        $clinicaId = $contexto instanceof Usuario ? (string) $contexto->tenant_id : (string) $contexto->id;

        return $this->dentroDe($contexto, fn (): Paciente => Paciente::factory()->create([
            'tenant_id' => $clinicaId,
        ]));
    }

    /**
     * Deja al profesional como tratante del paciente mediante una asignacion
     * de cuidado, que es la forma administrativa de esa relacion.
     */
    protected function asignarCuidado(Usuario $profesionalUsuario, Paciente $paciente): AsignacionCuidado
    {
        return $this->dentroDe($profesionalUsuario, fn (): AsignacionCuidado => AsignacionCuidado::query()->create([
            'tenant_id' => $profesionalUsuario->tenant_id,
            'paciente_id' => $paciente->id,
            'profesional_id' => $profesionalUsuario->profesional_id,
            'activo' => true,
            'motivo' => 'Asignacion de prueba',
        ]));
    }

    /**
     * Registra una evolucion, para tener historia clinica que proteger.
     */
    protected function crearEvolucion(Usuario $profesionalUsuario, Paciente $paciente): Evolución
    {
        return $this->dentroDe($profesionalUsuario, fn (): Evolucion => Evolucion::query()->create([
            'tenant_id' => $profesionalUsuario->tenant_id,
            'paciente_id' => $paciente->id,
            'profesional_id' => $profesionalUsuario->profesional_id,
            'fecha' => now(),
            'motivo_consulta' => 'Consulta de prueba',
            'diagnostico' => 'Diagnostico de prueba',
            'tratamiento' => 'Tratamiento de prueba',
        ]));
    }
}