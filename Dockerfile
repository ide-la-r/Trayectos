# ─── 1. Assets ───────────────────────────────────────────────────────────────
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# El plugin de Vite escribe en public/, así que el directorio debe existir
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
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
FROM dunglas/frankenphp:1-php8.4-alpine

# bcmath: los avisos firman con curva elíptica y sin él la librería lo hace
# en PHP puro, mucho más lento
RUN install-php-extensions pdo_pgsql pdo_sqlite gd intl zip opcache pcntl bcmath

# El binario de FrankenPHP trae CAP_NET_BIND_SERVICE marcada en el propio
# fichero para poder escuchar en el 80 sin ser root. Render —como cualquier
# plataforma que arranca con no-new-privileges— se niega a ejecutar un binario
# que gane privilegios: el exec falla con EPERM y el contenedor sale con status
# 126. El puerto que asigna Render está por encima de 1024, así que la
# capability no hace falta y se quita.
RUN apk add --no-cache libcap \
    && setcap -r /usr/local/bin/frankenphp

# Hace falta para regenerar el autoloader ya con el código de la aplicación
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# OPcache en producción: sin esto se recompila PHP en cada petición
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /app/docker/entrypoint.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

ENTRYPOINT ["/app/docker/entrypoint.sh"]
