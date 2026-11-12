#!/bin/sh
# Arranque del contenedor "app": prepara claves, espera a la base, migra y entrega el control a php-fpm.
set -eu
cd /var/www/html

mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

# APP_KEY: si no llega por entorno, se genera una vez y se guarda en el volumen de storage
# (no vive en el repositorio ni en la imagen).
if [ -z "${APP_KEY:-}" ]; then
    KEYFILE=storage/app/.app_key
    if [ ! -s "$KEYFILE" ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$KEYFILE"
        chmod 600 "$KEYFILE"
        echo "[rotor] APP_KEY generada y guardada en el volumen de storage."
    fi
    APP_KEY="$(cat "$KEYFILE")"
    export APP_KEY
fi

if [ "$1" = "php-fpm" ]; then
    php /usr/local/bin/wait-for-db.php

    if [ "${RUN_BOOTSTRAP:-true}" = "true" ]; then
        BOOT_ARGS=""
        [ "${SEED_DEMO:-true}" = "true" ] && BOOT_ARGS="$BOOT_ARGS --seed-demo"
        # ADMIN_EMAIL y ADMIN_PASSWORD las lee el comando directamente del entorno (no aparecen en la lista de procesos).
        # shellcheck disable=SC2086
        php artisan rotor:bootstrap $BOOT_ARGS
    fi

    php artisan config:cache
    php artisan event:cache
    chown -R www-data:www-data storage bootstrap/cache
fi

exec "$@"
