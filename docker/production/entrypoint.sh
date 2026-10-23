#!/bin/sh
# Se ejecuta cada vez que arranca un contenedor de PHP (app, queue o scheduler).
# CONTAINER_ROLE dice cuál es: solo "app" prepara la base de datos, para no hacerlo tres veces.
set -e
cd /var/www

if [ -z "$APP_KEY" ]; then
    echo "ERROR: falta la variable APP_KEY. Genérala con: php artisan key:generate --show" >&2
    exit 1
fi

# El volumen de storage puede venir vacío (primer despliegue) o de una versión anterior
mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
         storage/framework/sessions storage/framework/views storage/logs

# Caché de configuración, rutas y vistas con las variables de este servidor
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
    # MySQL tarda unos segundos en estar listo la primera vez
    intentos=0
    until php artisan migrate --force; do
        intentos=$((intentos + 1))
        if [ "$intentos" -ge 30 ]; then
            echo "ERROR: no se pudo conectar a la base de datos para migrar." >&2
            exit 1
        fi
        echo "Esperando a la base de datos... ($intentos)"
        sleep 2
    done

    # Recintos y pasarela de pago: se pueden correr siempre, no duplican nada
    php artisan db:seed --force

    # Bitácora (base eventos_bitacora, usuario de solo leer/agregar): también se puede repetir.
    # Si falla, la tienda sigue funcionando; el panel mostrará "bitácora no disponible".
    if [ "${BITACORA_HABILITADA:-true}" = "true" ]; then
        php artisan bitacora:instalar || echo "AVISO: no se pudo instalar la bitácora (revisa DB_ROOT_PASSWORD y BITACORA_DB_PASSWORD)." >&2
    fi
fi

exec "$@"
