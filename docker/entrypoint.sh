#!/bin/sh
set -e

# Render (y cualquier PaaS) inyecta el puerto por variable de entorno.
export SERVER_NAME=":${PORT:-80}"

echo "→ Preparando la aplicación"

# La caché de configuración se regenera en cada arranque: las variables de
# entorno cambian entre despliegues y una caché vieja las ignoraría.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrar al arrancar es aceptable con un solo contenedor. Con varios habría que
# sacarlo a un paso previo del despliegue para que no corran a la vez.
php artisan migrate --force --isolated || {
    echo "✗ Las migraciones han fallado"
    exit 1
}

echo "→ Servidor escuchando en ${SERVER_NAME}"

exec frankenphp run --config /etc/caddy/Caddyfile
