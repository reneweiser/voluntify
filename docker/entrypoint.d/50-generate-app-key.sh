#!/bin/sh
set -e

KEY_FILE="/var/www/html/storage/app/.app_key"
ENV_FILE="/var/www/html/.env"

# Coolify-provided APP_KEY takes precedence
if [ -n "$APP_KEY" ] && [ "$APP_KEY" != "base64:" ]; then
    echo "[app-key] Using APP_KEY from environment."
    echo "$APP_KEY" > "$KEY_FILE"
    chown www-data:www-data "$KEY_FILE"
    chmod 600 "$KEY_FILE"
    exit 0
fi

# Load persisted key from shared volume
if [ -f "$KEY_FILE" ] && [ -s "$KEY_FILE" ]; then
    APP_KEY=$(cat "$KEY_FILE")
    echo "[app-key] Loaded persisted APP_KEY from volume."
    echo "APP_KEY=$APP_KEY" > "$ENV_FILE"
    chown www-data:www-data "$ENV_FILE"
    export APP_KEY
    exit 0
fi

# First deploy: generate new key
echo "[app-key] No APP_KEY found, generating..."
APP_KEY=$(php artisan key:generate --show --no-interaction)
echo "$APP_KEY" > "$KEY_FILE"
chown www-data:www-data "$KEY_FILE"
chmod 600 "$KEY_FILE"
echo "APP_KEY=$APP_KEY" > "$ENV_FILE"
chown www-data:www-data "$ENV_FILE"
export APP_KEY
echo "[app-key] Generated and persisted new APP_KEY."
