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

# Stage 3 — Final FrankenPHP image
FROM dunglas/frankenphp:1-php8.4-bookworm

RUN install-php-extensions pcntl opcache pdo pdo_mysql intl zip gd exif ftp bcmath redis

RUN echo "opcache.enable=1" > /usr/local/etc/php/conf.d/custom.ini \
    && echo "opcache.jit=tracing" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "opcache.jit_buffer_size=256M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize=64M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size=64M" >> /usr/local/etc/php/conf.d/custom.ini

WORKDIR /app

COPY . .
COPY --from=composer-deps /app/vendor/ vendor/
COPY --from=node-build /app/public/build/ public/build/

COPY Caddyfile /etc/frankenphp/Caddyfile

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
