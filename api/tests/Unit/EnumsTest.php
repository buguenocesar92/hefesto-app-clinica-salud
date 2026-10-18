<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\EstadoCita;
use App\Enums\RolClinico;
use App\Enums\Sexo;
use App\Enums\TipoConsentimiento;
use PHPUnit\Framework\TestCase;

/**
 * Los enums que sostienen los permisos y los estados.
 *
 * Son codigo sin base de datos: se pueden correr sin PostgreSQL, y por eso
 * estan aqui y no mezclados con las pruebas de caracteristicas.
 */
class EnumsTest extends TestCase
{
    public function test_los_tres_roles_de_clinica_estan_definidos(): void
    {
        $this->assertSame(
            ['recepcion', 'profesional', 'administrador'],
            array_map(static fn (RolClinico $rol): string => $rol->value, RolClinico::cases())
        );
    }

    public function test_cada_rol_tiene_su_etiqueta_y_su_equipo_de_authentik(): void
    {
        foreach (RolClinico::cases() as $rol) {
            $this->assertNotSame('', $rol->etiqueta());
            $this->assertStringStartsWith('ClinicaSalud', $rol->equipoAuthentik());
        }
    }

    public function test_los_estados_finales_de_cita_no_ocupan_agenda(): void
    {
        $this->assertTrue(EstadoCita::Agendada->ocupaAgenda());
        $this->assertTrue(EstadoCita::Confirmada->ocupaAgenda());
        $this->assertTrue(EstadoCita::Atendida->ocupaAgenda());

        $this->assertFalse(EstadoCita::Cancelada->ocupaAgenda());
        $this->assertFalse(EstadoCita::NoAsistio->ocupaAgenda());
    }

    public function test_una_cita_atendida_o_cancelada_es_final(): void
    {
        $this->assertFalse(EstadoCita::Agendada->esFinal());
        $this->assertFalse(EstadoCita::Confirmada->esFinal());

        $this->assertTrue(EstadoCita::Atendida->esFinal());
        $this->assertTrue(EstadoCita::Cancelada->esFinal());
        $this->assertTrue(EstadoCita::NoAsistio->esFinal());
    }

    public function test_sexo_tiene_caso_para_no_informar(): void
    {
        // La ficha de identificacion debe poder completarse sin obligar a
        // decidir un dato que la persona no entrego.
        $this->assertNotNull(Sexo::tryFrom('N'));
        $this->assertSame(4, count(Sexo::cases()));
    }

    public function test_todo_consentimiento_tiene_etiqueta(): void
    {
        foreach (TipoConsentimiento::cases() as $tipo) {
            $this->assertNotSame('', $tipo->etiqueta());
        }
    }
}