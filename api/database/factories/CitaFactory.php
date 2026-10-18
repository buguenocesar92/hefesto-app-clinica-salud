<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoCita;
use App\Models\Cita;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cita>
 */
class CitaFactory extends Factory
{
    protected $model = Cita::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inicio = $this->faker->dateTimeBetween('+1 day', '+30 days');

        return [
            'inicio' => $inicio,
            'fin' => (clone $inicio)->modify('+30 minutes'),
            'estado' => EstadoCita::Agendada,
            'motivo_consulta' => $this->faker->sentence(4),
        ];
    }

    public function atendida(): static
    {
        return $this->state(fn (): array => ['estado' => EstadoCita::Atendida]);
    }
}