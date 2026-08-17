# ─── 1. Assets ───────────────────────────────────────────────────────────────
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ─── 2. Dependencias PHP ─────────────────────────────────────────────────────
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
# --no-scripts: los scripts de Laravel necesitan el código completo, que aún no está
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# ─── 3. Imagen final ─────────────────────────────────────────────────────────
# FrankenPHP trae servidor web y PHP en un único proceso: en 512 MB de RAM
# gratuitos, cada proceso que no arrancas es memoria que te queda.
FROM dunglas/frankenphp:php8.4-alpine

RUN install-php-extensions pdo_pgsql pdo_sqlite gd intl zip opcache pcntl

WORKDIR /app

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/entrypoint.sh

# OPcache en producción: sin esto se recompila PHP en cada petición
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    OCTANE_SERVER=frankenphp

ENTRYPOINT ["docker/entrypoint.sh"]
