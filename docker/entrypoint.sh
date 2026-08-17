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

# Sin --isolated a propósito: ese modo necesita el almacén de caché, que en esta
# aplicación es la propia base de datos y todavía no tiene tablas en el primer
# despliegue. Con un único contenedor no hay carrera que evitar; si algún día
# hay varias instancias, hay que sacar las migraciones a un paso previo.
php artisan migrate --force || {
    echo "✗ Las migraciones han fallado"
    exit 1
}

echo "→ Servidor escuchando en ${SERVER_NAME}"

exec frankenphp run --config /etc/caddy/Caddyfile
