#!/bin/sh
set -e

cd /var/www/html

# Clean clone: vendor/ is gitignored, so seed it from the image copy.
if [ ! -f vendor/autoload.php ]; then
    echo "==> vendor/ missing, installing dependencies"
    cp -R /opt/vendor vendor
    composer dump-autoload --optimize --no-interaction
fi

# Production takes its configuration from real environment variables, so a
# .env file is only written when one is genuinely needed (development).
if [ ! -f .env ] && [ -z "$APP_KEY" ]; then
    echo "==> .env missing, copying .env.example"
    cp .env.example .env
fi

if [ -f .env ] && ! grep -q '^APP_KEY=base64:' .env && [ -z "$APP_KEY" ]; then
    echo "==> generating APP_KEY"
    php artisan key:generate --force --no-interaction
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R a+rw storage bootstrap/cache

# Opt-in, so a restart never silently rewrites data you wanted to keep.
if [ "$MIGRATE_ON_BOOT" = "true" ]; then
    echo "==> waiting for the database"
    until php -r 'new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT")?:3306), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' 2>/dev/null; do
        sleep 2
    done

    if [ "$SEED_FRESH_ON_BOOT" = "true" ]; then
        echo "==> migrate:fresh --seed (demo data reset)"
        php artisan migrate:fresh --seed --force --no-interaction
    else
        echo "==> migrate"
        php artisan migrate --force --no-interaction
    fi
fi

exec "$@"
