# Versión fija con el parche de seguridad del 21/10/2026 (CVE-2026-109618 y CVE-2026-109619,
# ejecución remota de código; corregidas en 8.4.27). Para actualizar: cambiar aquí y
# reconstruir con `docker compose build --pull`.
FROM php:8.4.27-fpm

RUN apt-get update && apt-get install -y \
    git curl zip unzip libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libonig-dev libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# GD con JPEG y WebP: el banner del catálogo optimiza las imágenes a WebP
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd

# Límites de subida (el banner admite hasta 15 MB)
COPY docker/php/subidas.ini /usr/local/etc/php/conf.d/subidas.ini

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 9000
CMD ["php-fpm"]