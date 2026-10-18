<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Clinica;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Clinica>
 */
class ClinicaFactory extends Factory
{
    protected $model = Clinica::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = $this->faker->unique()->company() . ' Clinica';

        return [
            'slug' => Str::slug($nombre),
            'correlacion_identidad' => 'clinica-' . Str::lower(Str::random(8)),
            'nombre' => $nombre,
            'razon_social' => $nombre . ' Ltda.',
            'rut' => $this->faker->numerify('##.###.###-#'),
            'direccion' => $this->faker->address(),
            'telefono' => '+56 2 2### ####',
            'correo' => $this->faker->unique()->safeEmail(),
            'zona_horaria' => 'America/Santiago',
            'activo' => true,
        ];
    }

    /**
     * Clinica desactivada.
     */
    public function inactiva(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}