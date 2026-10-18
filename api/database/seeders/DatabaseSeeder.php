<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Clinica;
use Illuminate\Database\Seeder;

/**
 * Punto de entrada de la semilla.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ClinicaDeEjemploSeeder::class);
    }
}