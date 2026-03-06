FROM serversideup/php:8.5-fpm-nginx AS php_base

USER root
RUN install-php-extensions intl

WORKDIR /var/www/html

# Copy composer files only
COPY --chown=www-data:www-data ./composer.json ./composer.lock ./

# Install production PHP deps
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction


# -----------------------------------------------------
# NODE BUILD STAGE
# -----------------------------------------------------
FROM node:20 AS node_build

WORKDIR /app

# Copy only package files to use build cache
COPY ./package.json ./
COPY ./package-lock.json ./

RUN npm ci

# Copy vendor from php_base for Filament CSS imports
COPY --from=php_base /var/www/html/vendor ./vendor

# Copy full source
COPY . .

RUN npm run build


# -----------------------------------------------------
# FINAL RUNTIME IMAGE
# No node, no node_modules, only PHP + built assets
# -----------------------------------------------------
FROM php_base AS final

WORKDIR /var/www/html

# Copy full Laravel code
COPY --chown=www-data:www-data . .

# Copy built assets from node stage
COPY --from=node_build /app/public/build ./public/build

# Fix perms
RUN chmod -R 755 storage bootstrap/cache

# Auto-generate APP_KEY entrypoint (runs on app service via serversideup entrypoint.d)
COPY --chmod=755 docker/entrypoint.d/50-generate-app-key.sh /etc/entrypoint.d/50-generate-app-key.sh
# Key loader wrapper for scheduler/queue services (entrypoint.d skipped when AUTORUN_ENABLED=false)
COPY --chmod=755 docker/load-app-key.sh /usr/local/bin/load-app-key.sh

USER www-data
