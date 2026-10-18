<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Auth\DescubridorOidc;
use Illuminate\Console\Command;

/**
 * Descarta la cache de descubrimiento y de claves de Authentik.
 *
 * Se usa cuando Authentik rota su clave de firma y la API sigue rechazando
 * los tokens que si vienen bien: es el sintoma clasico de una clave que
 * quedo cacheada. Ver docs/05.
 */
class OlvidarCacheOidcCommand extends Command
{
    protected $signature = 'clinica:olvidar-cache-oidc';

    protected $description = 'Olvida la cache de descubrimiento y de claves (JWKS) de Authentik.';

    public function handle(DescubridorOidc $descubridor): int
    {
        if ((string) config('authentik.issuer') === '') {
            $this->error('No hay AUTHENTIK_ISSUER configurado: no hay nada que olvidar.');

            return self::FAILURE;
        }

        $descubridor->olvidarCache();

        $this->info('Cache de Authentik olvidada. El proximo ingreso la vuelve a leer.');

        return self::SUCCESS;
    }
}