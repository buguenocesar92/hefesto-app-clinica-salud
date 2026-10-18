<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RolClinico;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->name(),
            'correo' => $this->faker->unique()->safeEmail(),
            'rol' => RolClinico::Recepcion,
            'profesional_id' => null,
            'activo' => true,
        ];
    }

    public function recepcion(): static
    {
        return $this->state(fn (): array => ['rol' => RolClinico::Recepcion]);
    }

    public function administrador(): static
    {
        return $this->state(fn (): array => ['rol' => RolClinico::Administrador]);
    }

    public function profesional(?string $profesionalId = null): static
    {
        return $this->state(fn (): array => [
            'rol' => RolClinico::Profesional,
            'profesional_id' => $profesionalId,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}