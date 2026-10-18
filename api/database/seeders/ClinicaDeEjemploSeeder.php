<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EstadoCita;
use App\Enums\EstadoReceta;
use App\Enums\RolClinico;
use App\Enums\Sexo;
use App\Enums\TipoConsentimiento;
use App\Models\AsignacionCuidado;
use App\Models\Cita;
use App\Models\Clinica;
use App\Models\Consentimiento;
use App\Models\Evolucion;
use App\Models\Paciente;
use App\Models\Profesional;
use App\Models\Receta;
use App\Models\Sala;
use App\Models\Tarifa;
use App\Models\TipoAtencion;
use App\Models\Usuario;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Database\Seeder;

/**
 * Clinica de ejemplo, con dos profesionales, pacientes y agenda.
 *
 * PARA QUE SIRVE
 * --------------
 * Deja la aplicacion en un estado en que se puede abrir la agenda, entrar
 * con cada rol y comprobar que la separacion funciona:
 *
 *   - admin@clinicasalud.test        administracion de la clinica
 *   - recepcion@clinicasalud.test     recepcion
 *   - dra.gutierrez@clinicasalud.test profesional que trata a Maria
 *   - dr.espinoza@clinicasalud.test   profesional que trata a Jorge
 *
 * Ojo: estas cuentas solo funcionan si en Authentik existen las personas con
 * esos correos y con los equipos correspondientes. Ver docs/06.
 *
 * POR QUE TODAS LAS ESCRITURAS VAN DENTRO DE `ejecutarAislado`
 * -----------------------------------------------------------
 * Las tablas de negocio tienen Row Level Security forzado, asi que una
 * escritura sin contexto de clinica NO se guarda: falla. La semilla no es una
 * excepcion: escribir filas de una clinica sin declarar de que clinica son
 * seria justamente el fallo que la RLS existe para evitar.
 */
class ClinicaDeEjemploSeeder extends Seeder
{
    public function run(): void
    {
        // `clinicas` no tiene RLS: se puede leer y escribir sin contexto.
        $clinica = Clinica::query()->updateOrCreate(
            ['correlacion_identidad' => 'centro-vida'],
            [
                'slug' => 'centro-vida-medica',
                'nombre' => 'Centro de Vida Medica',
                'razon_social' => 'Centro de Vida Medica Ltda.',
                'rut' => '76.543.210-9',
                'direccion' => 'Av. Siempre Viva 742, of. 903, Providencia, Santiago',
                'telefono' => '+56 2 2345 6789',
                'correo' => 'contacto@centrovida.test',
                'zona_horaria' => 'America/Santiago',
                'activo' => true,
            ]
        );

        app(ContextoTenant::class)->ejecutarAislado($clinica->id, function () use ($clinica): void {
            $salas = $this->salas();
            $tipos = $this->tiposDeAtencion();
            $profesionales = $this->profesionales();

            $this->tarifas($tipos);
            $this->cuentas($profesionales);
            $pacientes = $this->pacientes();
            $this->consentimientos($pacientes);
            $citas = $this->agenda($pacientes, $profesionales, $salas, $tipos);
            $this->historiaClinica($pacientes, $profesionales, $citas);
            $this->equiposDeCuidado($pacientes, $profesionales);
        });
    }

    /**
     * Agrega el `tenant_id` del contexto actual a los atributos de alta.
     *
     * Que la semilla no pueda saltarse esto es intencional: si el contexto no
     * esta fijado, `clinicaIdEnConexion()` devuelve null y el alta falla en
     * la base por la restriccion `WITH CHECK`.
     *
     * @param  array<string, mixed>  $atributos
     * @return array<string, mixed>
     */
    private function con(array $atributos): array
    {
        return array_merge($atributos, [
            'tenant_id' => app(ContextoTenant::class)->clinicaIdEnConexion(),
        ]);
    }

    /**
     * @return array<string, Sala>
     */
    private function salas(): array
    {
        return [
            'consulta1' => Sala::query()->create($this->con(['nombre' => 'Consulta 1', 'piso' => '9'])),
            'consulta2' => Sala::query()->create($this->con(['nombre' => 'Consulta 2', 'piso' => '9'])),
            'procedimientos' => Sala::query()->create($this->con(['nombre' => 'Sala de procedimientos', 'piso' => '9'])),
        ];
    }

    /**
     * @return array<string, TipoAtencion>
     */
    private function tiposDeAtencion(): array
    {
        return [
            'consulta' => TipoAtencion::query()->create($this->con([
                'nombre' => 'Consulta medica general',
                'duracion_minutos' => 30,
                'color' => '#2f6f4e',
            ])),
            'control' => TipoAtencion::query()->create($this->con([
                'nombre' => 'Control de enfermedad cronica',
                'duracion_minutos' => 30,
                'color' => '#3b6ea5',
            ])),
            'procedimiento' => TipoAtencion::query()->create($this->con([
                'nombre' => 'Procedimiento menor',
                'duracion_minutos' => 45,
                'color' => '#8a5a2b',
            ])),
        ];
    }

    /**
     * @return array<string, Profesional>
     */
    private function profesionales(): array
    {
        return [
            'gutierrez' => Profesional::query()->create($this->con([
                'nombre' => 'Dra. Valentina Gutierrez Rojas',
                'profesion' => 'Medica general',
                'registro_profesional' => 'MP-12.345',
                'correo' => 'dra.gutierrez@clinicasalud.test',
            ])),
            'espinoza' => Profesional::query()->create($this->con([
                'nombre' => 'Dr. Rodrigo Espinoza Fuentes',
                'profesion' => 'Medico general',
                'registro_profesional' => 'MP-15.902',
                'correo' => 'dr.espinoza@clinicasalud.test',
            ])),
        ];
    }

    /**
     * @param  array<string, TipoAtencion>  $tipos
     */
    private function tarifas(array $tipos): void
    {
        Tarifa::query()->create($this->con([
            'tipo_atencion_id' => $tipos['consulta']->id,
            'concepto' => 'Consulta medica particular',
            'valor' => 30000,
        ]));

        Tarifa::query()->create($this->con([
            'tipo_atencion_id' => $tipos['control']->id,
            'concepto' => 'Control de enfermedad cronica',
            'valor' => 25000,
        ]));

        Tarifa::query()->create($this->con([
            'tipo_atencion_id' => $tipos['procedimiento']->id,
            'concepto' => 'Procedimiento menor',
            'valor' => 55000,
        ]));

        Tarifa::query()->create($this->con([
            'tipo_atencion_id' => null,
            'concepto' => 'Certificado medico simple',
            'valor' => 8000,
        ]));
    }

    /**
     * @param  array<string, Profesional>  $profesionales
     */
    private function cuentas(array $profesionales): void
    {
        Usuario::query()->create($this->con([
            'nombre' => 'Direccion Centro de Vida',
            'correo' => 'admin@clinicasalud.test',
            'rol' => RolClinico::Administrador,
            'activo' => true,
        ]));

        Usuario::query()->create($this->con([
            'nombre' => 'Recepcion turno manana',
            'correo' => 'recepcion@clinicasalud.test',
            'rol' => RolClinico::Recepcion,
            'activo' => true,
        ]));

        Usuario::query()->create($this->con([
            'nombre' => 'Dra. Valentina Gutierrez Rojas',
            'correo' => 'dra.gutierrez@clinicasalud.test',
            'rol' => RolClinico::Profesional,
            'profesional_id' => $profesionales['gutierrez']->id,
            'activo' => true,
        ]));

        Usuario::query()->create($this->con([
            'nombre' => 'Dr. Rodrigo Espinoza Fuentes',
            'correo' => 'dr.espinoza@clinicasalud.test',
            'rol' => RolClinico::Profesional,
            'profesional_id' => $profesionales['espinoza']->id,
            'activo' => true,
        ]));
    }

    /**
     * @return array<string, Paciente>
     */
    private function pacientes(): array
    {
        return [
            'maria' => Paciente::query()->create($this->con([
                'rut' => '15.555.666-7',
                'nombres' => 'Maria',
                'apellidos' => 'Fernandez Lopez',
                'fecha_nacimiento' => '1984-03-12',
                'sexo' => Sexo::Femenino,
                'telefono' => '+56 9 8877 6655',
                'correo' => 'maria.fernandez@example.test',
                'direccion' => 'Las Camelias 1148, Conchalaba',
                'cobertura' => 'Fonasa B',
            ])),
            'jorge' => Paciente::query()->create($this->con([
                'rut' => '18.222.333-4',
                'nombres' => 'Jorge',
                'apellidos' => 'Muniz Carter',
                'fecha_nacimiento' => '1996-11-02',
                'sexo' => Sexo::Masculino,
                'telefono' => '+56 9 5544 3322',
                'correo' => 'jorge.muniz@example.test',
                'direccion' => 'Av. Vespucio Sur 2210, Macul',
                'cobertura' => 'Isapre VidaCo',
            ])),
            'lucia' => Paciente::query()->create($this->con([
                'rut' => '22.888.999-1',
                'nombres' => 'Lucia',
                'apellidos' => 'Perez Nunez',
                'fecha_nacimiento' => '2015-07-30',
                'sexo' => Sexo::Femenino,
                'telefono' => '+56 9 2233 1144',
                'correo' => null,
                'direccion' => 'Los Robles 55, Lo Barnechea',
                'cobertura' => 'Fonasa A',
            ])),
        ];
    }

    /**
     * @param  array<string, Paciente>  $pacientes
     */
    private function consentimientos(array $pacientes): void
    {
        foreach ($pacientes as $paciente) {
            Consentimiento::query()->create($this->con([
                'paciente_id' => $paciente->id,
                'tipo' => TipoConsentimiento::Tratamiento,
                'otorgado' => true,
                'fecha' => '2026-01-15',
                'documento_referencia_externo' => 'docuseal:plantilla-consentimiento-' . substr($paciente->id, 0, 8),
                'notas' => 'Consentimiento impreso y firmado en recepcion.',
            ]));
        }
    }

    /**
     * @param  array<string, Paciente>  $pacientes
     * @param  array<string, Profesional>  $profesionales
     * @param  array<string, Sala>  $salas
     * @param  array<string, TipoAtencion>  $tipos
     * @return array<string, Cita>
     */
    private function agenda(array $pacientes, array $profesionales, array $salas, array $tipos): array
    {
        $hoy = now()->startOfDay();

        return [
            'maria_hoy' => Cita::query()->create($this->con([
                'paciente_id' => $pacientes['maria']->id,
                'profesional_id' => $profesionales['gutierrez']->id,
                'sala_id' => $salas['consulta1']->id,
                'tipo_atencion_id' => $tipos['control']->id,
                'inicio' => $hoy->copy()->addHours(10),
                'fin' => $hoy->copy()->addHours(10)->addMinutes(30),
                'estado' => EstadoCita::Confirmada,
                'motivo_consulta' => 'Control de hipertension',
            ])),
            'jorge_hoy' => Cita::query()->create($this->con([
                'paciente_id' => $pacientes['jorge']->id,
                'profesional_id' => $profesionales['espinoza']->id,
                'sala_id' => $salas['consulta2']->id,
                'tipo_atencion_id' => $tipos['consulta']->id,
                'inicio' => $hoy->copy()->addHours(11),
                'fin' => $hoy->copy()->addHours(11)->addMinutes(30),
                'estado' => EstadoCita::Agendada,
                'motivo_consulta' => 'Dolor abdominal',
            ])),
            'lucia_manana' => Cita::query()->create($this->con([
                'paciente_id' => $pacientes['lucia']->id,
                'profesional_id' => $profesionales['gutierrez']->id,
                'sala_id' => $salas['consulta1']->id,
                'tipo_atencion_id' => $tipos['consulta']->id,
                'inicio' => $hoy->copy()->addDay()->addHours(9),
                'fin' => $hoy->copy()->addDay()->addHours(9)->addMinutes(30),
                'estado' => EstadoCita::Agendada,
                'motivo_consulta' => 'Control pediatrico',
            ])),
        ];
    }

    /**
     * @param  array<string, Paciente>  $pacientes
     * @param  array<string, Profesional>  $profesionales
     * @param  array<string, Cita>  $citas
     */
    private function historiaClinica(array $pacientes, array $profesionales, array $citas): void
    {
        Evolucion::query()->create($this->con([
            'paciente_id' => $pacientes['maria']->id,
            'profesional_id' => $profesionales['gutierrez']->id,
            'cita_id' => $citas['maria_hoy']->id,
            'fecha' => now()->subDays(30)->setTime(10, 0),
            'motivo_consulta' => 'Control de hipertension',
            'codigo_cie10' => 'I10',
            'diagnostico' => 'Hipertension arterial esencial',
            'tratamiento' => 'Se mantiene el tratamiento habitual y se solicita control semanal de la presion arterial en el domicilio.',
            'indicaciones' => 'Registrar la presion arterial tres veces por semana y traer el registro a la proxima consulta.',
        ]));

        Receta::query()->create($this->con([
            'paciente_id' => $pacientes['maria']->id,
            'profesional_id' => $profesionales['gutierrez']->id,
            'cita_id' => $citas['maria_hoy']->id,
            'fecha' => now()->subDays(30)->setTime(10, 10),
            'contenido' => "1. Losartan 50 mg, un comprimido al dia, en la manana.\n2. Acido acetilsalicilico 100 mg, un comprimido al dia.",
            'estado' => EstadoReceta::Emitida,
            'emitida_en' => now()->subDays(30)->setTime(10, 12),
            'documento_referencia_externo' => 'docuseal:plantilla-receta-0004821',
        ]));
    }

    /**
     * @param  array<string, Paciente>  $pacientes
     * @param  array<string, Profesional>  $profesionales
     */
    private function equiposDeCuidado(array $pacientes, array $profesionales): void
    {
        AsignacionCuidado::query()->create($this->con([
            'paciente_id' => $pacientes['maria']->id,
            'profesional_id' => $profesionales['gutierrez']->id,
            'activo' => true,
            'motivo' => 'Medica tratante principal',
        ]));

        AsignacionCuidado::query()->create($this->con([
            'paciente_id' => $pacientes['maria']->id,
            'profesional_id' => $profesionales['espinoza']->id,
            'activo' => true,
            'motivo' => 'Segunda opinion solicitada por la familia',
        ]));
    }
}