FROM php:8.2-cli

# Instalar dependencias y extensiones para PostgreSQL
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Establecer el directorio de trabajo
WORKDIR /app

# Copiar todo el código
COPY . .

# Instalar dependencias de Laravel
RUN composer install --no-dev --optimize-autoloader

# Dar permisos necesarios
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Comando para migrar la base de datos y encender el servidor
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
