# --- Etapa 1: Compilar Frontend (Node 16 es perfecto para Mix 6) ---
FROM node:16-alpine as build-stage

WORKDIR /app

COPY package*.json ./
# Mix 6 instala sus dependencias automáticamente, pero aseguramos limpieza
RUN npm install

COPY webpack.mix.js ./
COPY resources/ resources/
COPY public/ public/

# Mix 6 usa este comando estándar
RUN npm run production

# --- Etapa 2: Laravel (PHP 8.1 - Compatible con Laravel 8.75) ---
FROM php:8.1-apache

# Instalamos dependencias del sistema y librerías gráficas (gd)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath

# Activamos módulo rewrite para Apache
RUN a2enmod rewrite

# Apuntamos Apache a la carpeta public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiamos archivos de dependencias primero
COPY composer.json composer.lock ./

# Instalamos dependencias de PHP
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copiamos el resto del código
COPY . .

# Traemos los assets compilados del frontend (Mix-manifest es vital)
COPY --from=build-stage /app/public/js /var/www/html/public/js
COPY --from=build-stage /app/public/css /var/www/html/public/css
COPY --from=build-stage /app/mix-manifest.json /var/www/html/mix-manifest.json

# Permisos de carpetas
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

# Comando de arranque con MIGRACIÓN AUTOMÁTICA y optimizaciones
# Usamos --force para evitar preguntas en producción
CMD php artisan migrate --force && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    apache2-foreground