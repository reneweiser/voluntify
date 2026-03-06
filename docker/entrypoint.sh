#!/bin/sh
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan migrate --force

exec /usr/local/bin/docker-entrypoint.sh unitd --no-daemon
