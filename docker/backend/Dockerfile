# ============================================================
# Backend – Laravel 12 / PHP 8.2-FPM
# ============================================================
FROM php:8.4-fpm

# --- Dependencias del sistema ---
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# --- Extensiones PHP ---
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        opcache

# --- Extensión Redis ---
RUN pecl install redis && docker-php-ext-enable redis

# --- Composer ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Directorio de trabajo ---
WORKDIR /var/www/html

# --- Copiar código del backend y config de PHP ---
COPY backend/ .
COPY docker/backend/php.ini       /usr/local/etc/php/conf.d/custom.ini
COPY docker/backend/www.conf      /usr/local/etc/php-fpm.d/www.conf
COPY docker/backend/entrypoint.sh /entrypoint.sh

# --- Guardar plantillas fuera del volumen para copiarlas en tiempo de ejecución ---
RUN mkdir -p /var/www/html/storage-image/plantillas \
    && cp -r storage/app/plantillas/. /var/www/html/storage-image/plantillas/ 2>/dev/null || true \
    && chmod +x /entrypoint.sh

# --- Instalar dependencias y generar autoload en un solo paso ---
RUN git config --global --add safe.directory /var/www/html \
    && composer install \
        --no-dev \
        --optimize-autoloader \
        --prefer-dist \
        --no-interaction

# --- Eliminar caché de configuración para que cada deploy lea las env vars frescas ---
RUN rm -f bootstrap/cache/config.php \
         bootstrap/cache/routes.php \
         bootstrap/cache/packages.php \
         bootstrap/cache/services.php

# --- Permisos para Laravel ---
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
