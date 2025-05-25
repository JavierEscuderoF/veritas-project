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

# Opcional: Cambiar configuración de PHP (ej: upload_max_filesize)
# RUN echo "upload_max_filesize=32M" > /usr/local/etc/php/conf.d/uploads.ini
# RUN echo "post_max_size=32M" >> /usr/local/etc/php/conf.d/uploads.ini

# Permisos (si es necesario, aunque montar desde el host puede manejar esto)
# RUN chown -R www-data:www-data /var/www/html
