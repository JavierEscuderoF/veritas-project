FROM php:8.2-fpm-alpine

# Instalar dependencias comunes y extensiones PHP
RUN apk add --no-cache \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libxml2-dev \
    icu-dev \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo pdo_mysql \
        zip \
        intl \
        opcache \
        exif

# Opcional: Instalar Composer (gestor de dependencias para PHP)
# COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Directorio de trabajo
WORKDIR /var/www/html

COPY conf.d/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
