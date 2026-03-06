#!/bin/sh
set -e

# Ensure storage directory structure exists (volume mount may be empty)
mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app/public

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan migrate --force
php artisan storage:link --force

exec /usr/local/bin/docker-entrypoint.sh unitd --no-daemon
