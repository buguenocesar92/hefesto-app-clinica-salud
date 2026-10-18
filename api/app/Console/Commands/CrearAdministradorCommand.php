<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RolClinico;
use App\Models\Clinica;
use App\Models\Usuario;
use Illuminate\Console\Command;

/**
 * Crea la primera cuenta de administracion de una clinica.
 *
 * Esta cuenta NO se puede crear desde la interfaz: el despliegue la deja lista,
 * con un correo del dominio de la clinica, y a partir de ahi el administrador
 * da de alta al resto. Ver docs/04.
 *
 *     php artisan clinica:crear-administrador clinica=centro-vida-medica \
 *         correo=admin@centrovida.cl nombre="Direccion Medica"
 */
class CrearAdministradorCommand extends Command
{
    protected $signature = 'clinica:crear-administrador
        {clinica : Correlacion de identidad de la clinica (columna clinicas.correlacion_identidad)}
        {correo : Correo de la persona, que sera su llave de enlace con Authentik}
        {nombre : Nombre que se muestra en la interfaz}';

    protected $description = 'Crea la primera cuenta de administracion de una clinica.';

    public function handle(): int
    {
        $correlacion = (string) $this->argument('clinica');
        $correo = mb_strtolower((string) $this->argument('correo'));
        $nombre = (string) $this->argument('nombre');

        if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('El correo no tiene un formato valido.');

            return self::FAILURE;
        }

        $clinica = Clinica::query()->where('correlacion_identidad', $correlacion)->first();

        if ($clinica === null) {
            $this->error('No existe una clinica con la correlacion "' . $correlacion . '".');
            $this->line('Las correlaciones existentes:');

            Clinica::query()->orderBy('nombre')->get(['correlacion_identidad', 'nombre'])
                ->each(fn (Clinica $fila) => $this->line('  - ' . $fila->correlacion_identidad . ' : ' . $fila->nombre));

            return self::FAILURE;
        }

        $existente = Usuario::query()->where('tenant_id', $clinica->id)->where('correo', $correo)->first();

        if ($existente !== null) {
            $this->error('Ya existe una cuenta con ese correo en esa clinica (id ' . $existente->id . ').');

            return self::FAILURE;
        }

        $usuario = Usuario::query()->create([
            'tenant_id' => $clinica->id,
            'nombre' => $nombre,
            'correo' => $correo,
            'rol' => RolClinico::Administrador,
            'activo' => true,
        ]);

        $this->info('Administrador creado: ' . $usuario->correo . ' (' . $clinica->nombre . ').');
        $this->line('Para entrar, esa persona debe iniciar sesion en Authentik con ese correo.');
        $this->line('Si no puede entrar, revise que el equipo de Authentik "' . RolClinico::Administrador->equipoAuthentik() . '" lo incluya.');

        return self::SUCCESS;
    }
}