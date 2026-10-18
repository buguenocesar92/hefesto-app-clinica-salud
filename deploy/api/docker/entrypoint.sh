#!/bin/sh
# =============================================================================
# Entrada de la API de ClinicaSalud
# =============================================================================
# Deja la aplicacion en estado ejecutable: permisos, cache de configuracion y
# migraciones.
#
# POR QUE LAS MIGRACIONES CORREN AL ARRANQUE
# ------------------------------------------
# En una clinica hay una sola base y el despliegue lo hace el operador o el
# estudio. Correr las migraciones aqui evita el paso manual que siempre se
# olvida. El bloqueo de PostgreSQL hace que dos réplicas arrancando a la vez no
# corran migraciones en paralelo.
# =============================================================================

set -eu

cd /var/www/html

if [ ! -f .env ]; then
    echo "ClinicaSalud: falta el archivo .env. Copie api/.env.example y complete los valores." >&2
    exit 1
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "ClinicaSalud: APP_KEY vacio. Ejecute 'php artisan key:generate' y guarde el resultado." >&2
    exit 1
fi

echo "ClinicaSalud: preparando permisos..."
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

echo "ClinicaSalud: limpiando caches..."
php artisan config:clear --quiet
php artisan route:clear --quiet
php artisan view:clear --quiet

echo "ClinicaSalud: ejecutando migraciones..."
php artisan migrate --force --no-interaction

if [ "${APP_ENV:-production}" = "production" ]; then
    echo "ClinicaSalud: compilando caches..."
    php artisan config:cache --quiet
    php artisan route:cache --quiet
    php artisan view:cache --quiet
fi

exec "$@"