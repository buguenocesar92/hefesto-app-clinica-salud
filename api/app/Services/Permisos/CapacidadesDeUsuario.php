<?php

declare(strict_types=1);

namespace App\Services\Permisos;

use App\Enums\RolClinico;
use App\Models\Paciente;
use App\Models\Usuario;

/**
 * Capacidades de una persona dentro de su clinica.
 *
 * Aca esta el matiz que define el producto:
 *
 *   - "Puede agendar" y "puede leer historia clinica" NO son lo mismo.
 *   - Recepcion agenda, ve datos de identificacion y cobertura, firma
 *     consentimientos... y NO puede abrir una ficha clinica. Ni la de un
 *     paciente que tiene cita con ella: eso no cambia nada.
 *   - El administrador de la clinica tampoco lee fichas clinicas salvo que
 *     ademas sea profesional. La direccion de la clinica no es un cargo
 *     clinico, y el acceso a datos de salud se justifica por la atencion, no
 *     por la jerarquia. Si una clinica necesita otra cosa, se resuelve
 *     creando un usuario con rol `profesional` y su ficha de profesional.
 *
 * Este trait responde preguntas; quien las aplica son las Policies
 * (App\Policies). Ver docs/03.
 */
trait CapacidadesDeUsuario
{
    /**
     * Administracion de la clinica: profesionales, salas, tarifas, usuarios y
     * bitacora.
     */
    public function puedeAdministrar(): bool
    {
        return $this->rol === RolClinico::Administrador && $this->activo;
    }

    /**
     * Agendar y mover citas, registrar pacientes, tomar consentimientos.
     *
     * Los tres roles pueden. Es la capacidad "de mostrador", no la clinica.
     */
    public function puedeAgendar(): bool
    {
        return $this->activo;
    }

    /**
     * Ver el detalle clinico de una ficha. Solo el profesional tratante.
     *
     * @see EquipoDeCuidado para las tres formas de ser tratante.
     */
    public function puedeLeerFicha(Paciente $paciente): bool
    {
        if (! $this->activo || $this->rol !== RolClinico::Profesional) {
            return false;
        }

        return app(EquipoDeCuidado::class)->incluye($this, $paciente);
    }

    /**
     * Escribir en la historia clinica: registrar una evolucion.
     */
    public function puedeRegistrarEvolucion(Paciente $paciente): bool
    {
        return $this->puedeLeerFicha($paciente);
    }

    /**
     * Emitir recetas y certificados.
     */
    public function puedeEmitirReceta(Paciente $paciente): bool
    {
        return $this->puedeLeerFicha($paciente);
    }

    /**
     * Es el profesional de este usuario.
     */
    public function esProfesional(): bool
    {
        return $this->rol === RolClinico::Profesional && $this->profesional_id !== null;
    }

    /**
     * Resumen de capacidades para la interfaz.
     *
     * La interfaz usa esto para mostrar u ocultar botones. NO es una defensa:
     * la defensa esta en el servidor. Que un boton no aparezca no impide que
     * alguien llame a la API, y por eso cada ruta vuelve a preguntar. Ver
     * docs/09.
     */
    public function capacidades(): array
    {
        return [
            'puede_agendar' => $this->puedeAgendar(),
            'puede_administrar' => $this->puedeAdministrar(),
            'puede_leer_fichas' => $this->rol === RolClinico::Profesional && $this->activo,
        ];
    }
}