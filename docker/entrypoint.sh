#!/bin/sh
set -e

cd /var/www/html

# Wait for DB if configured
if [ -n "${DB_HOST:-}" ] && [ -n "${DB_PORT:-}" ]; then
    echo "Waiting for ${DB_HOST}:${DB_PORT}..."
    timeout=60
    until php -r "exit(@fsockopen('${DB_HOST}', ${DB_PORT}) ? 0 : 1);" 2>/dev/null; do
        timeout=$((timeout - 1))
        if [ "$timeout" -le 0 ]; then
            echo "DB never came up — exiting"
            exit 1
        fi
        sleep 1
    done
    echo "DB is up"
fi

# Generate APP_KEY only if missing AND not provided via env
if [ -z "${APP_KEY:-}" ] && [ ! -f .env ]; then
    php artisan key:generate --show > /tmp/genkey 2>/dev/null || true
fi

# Migrations: only the service marked DB_OWNER=true runs them.
# In prod, admin owns the schema; api just consumes it.
if [ "${DB_OWNER:-false}" = "true" ] && [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "Running migrations as ${APP_SERVICE:-unknown}..."
    php artisan migrate --force --no-interaction || {
        echo "Migrations failed — exiting"
        exit 1
    }

    if [ "${RUN_SEEDERS:-false}" = "true" ]; then
        echo "Running seeders..."
        php artisan db:seed --force --no-interaction || true
    fi
fi

# Cache config + routes for prod
if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache --no-interaction || true
    php artisan route:cache --no-interaction || true
fi

exec "$@"
