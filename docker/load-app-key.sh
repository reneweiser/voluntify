#!/bin/sh
set -e

KEY_FILE="/var/www/html/storage/app/.app_key"

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    if [ -f "$KEY_FILE" ] && [ -s "$KEY_FILE" ]; then
        export APP_KEY=$(cat "$KEY_FILE")
        echo "APP_KEY=$APP_KEY" > /var/www/html/.env
        echo "[app-key] Loaded APP_KEY from shared volume."
    else
        echo "[app-key] WARNING: No APP_KEY found in environment or volume!"
    fi
fi

exec "$@"
