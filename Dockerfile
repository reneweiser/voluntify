# Stage 1 — Install PHP dependencies
FROM composer:latest AS composer-deps

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader

COPY . .

RUN composer dump-autoload --optimize

# Stage 2 — Build frontend assets
FROM node:22-alpine AS node-build

WORKDIR /app

COPY package.json package-lock.json vite.config.js ./
COPY resources/ resources/
COPY public/ public/
COPY --from=composer-deps /app/vendor/ vendor/

RUN npm ci && npm run build

# Stage 3 — Final NGINX Unit image
FROM unit:1.34.2-php8.4

RUN apt update && apt install -y \
    curl unzip git libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pcntl opcache pdo pdo_mysql intl zip gd exif ftp bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt clean && rm -rf /var/lib/apt/lists/*

RUN echo "opcache.enable=1" > /usr/local/etc/php/conf.d/custom.ini \
    && echo "opcache.jit=tracing" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "opcache.jit_buffer_size=256M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize=64M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size=64M" >> /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

COPY . .
COPY --from=composer-deps /app/vendor/ vendor/
COPY --from=node-build /app/public/build/ public/build/

COPY unit.json /docker-entrypoint.d/unit.json

RUN chown -R unit:unit storage bootstrap/cache && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
