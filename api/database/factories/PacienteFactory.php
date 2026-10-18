<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Sexo;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paciente>
 */
class PacienteFactory extends Factory
{
    protected $model = Paciente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rut' => $this->faker->unique()->numerify('##.###.###-#'),
            'nombres' => $this->faker->firstName(),
            'apellidos' => $this->faker->lastName(),
            'fecha_nacimiento' => $this->faker->dateTimeBetween('-80 years', '-1 year'),
            'sexo' => $this->faker->randomElement([
                Sexo::Femenino,
                Sexo::Masculino,
                Sexo::Otro,
                Sexo::NoInformado,
            ]),
            'telefono' => '+56 9 #### ####',
            'correo' => $this->faker->safeEmail(),
            'direccion' => $this->faker->address(),
            'cobertura' => $this->faker->randomElement(['Fonasa A', 'Fonasa B', 'Fonasa C', 'Isapre VidaCo', 'Particular']),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}