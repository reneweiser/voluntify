# Stage 1 — Install PHP dependencies
FROM composer:2.10.3@sha256:da624c2c705d54e2f5a9bb77a5dc6c7d091711433ebefd7035672e84c2010fee AS composer-deps

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader

COPY . .

RUN composer dump-autoload --optimize

# Stage 2 — Build frontend assets
FROM node:22.23.4-alpine3.24@sha256:fe4f6d74e20370acb04ad889077d14d0f64a9c8ea6a42bc60cddf1e844699914 AS node-build

WORKDIR /app

COPY package.json package-lock.json vite.config.js ./
COPY resources/ resources/
COPY public/ public/
COPY --from=composer-deps /app/vendor/ vendor/

RUN npm ci && npm run build

# Stage 3 — Final FrankenPHP image
# PHP 8.4.27 fixes CVE-2026-109618 and CVE-2026-109619.
FROM dunglas/frankenphp:1.13.2-php8.4.27-bookworm@sha256:49369ff211d81dc30a15890d58c0651d639aef0d9c0a9b91bcc6f387a77bd7a2

ARG APP_VERSION
ENV APP_VERSION=${APP_VERSION}

RUN install-php-extensions pcntl opcache pdo pdo_mysql intl zip gd exif ftp bcmath redis

RUN echo "opcache.enable=1" > /usr/local/etc/php/conf.d/custom.ini \
    && echo "opcache.jit=function" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "opcache.jit_buffer_size=128M" >> /usr/local/etc/php/conf.d/custom.ini \
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
