# =============================================================================
# Hefesto Studio — ClinicaSalud: imagen de la API
# =============================================================================
# Imagen unica con php-fpm, nginx y supervisord. Es lo que usan las demas
# aplicaciones de Hefesto, y evita depender de un servidor web externo para
# una instalacion de una sola clinica.
#
# Versiones fijadas, consultadas el 2026-10-17:
#   - php:8.4-fpm-alpine3.24   (Docker Hub, etiqueta existente)
#   - nginx:1.29-alpine        (Docker Hub, etiqueta existente)
# =============================================================================

FROM php:8.4-fpm-alpine3.24 AS base

# `pcntl` lo necesita el worker de colas; `pdo_pgsql`, la conexion a
# PostgreSQL; `bcmath` y `intl`, calculos y formato de fechas.
RUN apk add --no-cache \
        nginx \
        supervisor \
        libpq-dev \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
        bash \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del --no-network .build-deps \
    && rm -rf /tmp/*

WORKDIR /var/www/html

# Se copian primero los manifiestos para que la capa de dependencias se
# reutilice mientras no cambien las versiones.
COPY api/composer.json api/composer.lock* ./

RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

COPY api/ ./

RUN composer install \
        --no-dev \
        --no-scripts \
        --optimize-autoloader \
        --no-interaction \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY deploy/api/docker/php.ini /usr/local/etc/php/conf.d/zz-clinica.ini
COPY deploy/api/docker/nginx.conf /etc/nginx/http.d/clinica.conf
COPY deploy/api/docker/supervisord.conf /etc/supervisord.conf
COPY deploy/api/docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8000

# La salud no depende de la sesion ni de la clinica: solo comprueba que PHP
# responde. Un chequeo que necesita sesion daria falso negativo durante un
# despliegue.
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8000/api/v1/salud") === false ? 1 : 0);'

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]