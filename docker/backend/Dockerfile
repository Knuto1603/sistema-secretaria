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

# --- Copiar todo el código primero ---
COPY . .

# --- Instalar dependencias y generar autoload en un solo paso ---
RUN git config --global --add safe.directory /var/www/html \
    && composer install \
        --no-dev \
        --optimize-autoloader \
        --prefer-dist \
        --no-interaction

# --- Permisos para Laravel ---
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
