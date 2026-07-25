#!/bin/sh
set -e

cd /var/www/html

# Clean clone: vendor/ is gitignored, so seed it from the image copy.
if [ ! -f vendor/autoload.php ]; then
    echo "==> vendor/ missing, installing dependencies"
    cp -R /opt/vendor vendor
    composer dump-autoload --optimize --no-interaction
fi

# Production configuration comes from real environment variables. Check it
# BEFORE the development .env fallback below — otherwise a production container
# quietly boots on .env.example, whose APP_KEY is empty, and every request
# returns a bare 500 because APP_DEBUG is off.
if [ "$APP_ENV" = "production" ]; then
    # Both an empty and a malformed key produce the same symptom otherwise: a
    # bare 500 on every request, with APP_DEBUG=false hiding the reason.
    key_problem=$(php -r '
        $k = (string) getenv("APP_KEY");
        if ($k === "") { echo "APP_KEY is not set"; exit; }
        if (str_starts_with($k, "base64:")) { $k = base64_decode(substr($k, 7), true); }
        if ($k === false) { echo "APP_KEY is not valid base64"; exit; }
        $len = strlen($k);
        if ($len !== 32) { echo "APP_KEY decodes to {$len} bytes, AES-256-CBC needs 32"; }
    ')

    if [ -n "$key_problem" ]; then
        echo "FATAL: $key_problem." >&2
        echo "       Quotes and stray whitespace in .env.production are the usual cause." >&2
        echo "       Generate a fresh one:" >&2
        echo "       docker run --rm php:8.5-cli php -r \"echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;\"" >&2
        exit 1
    fi
else
    if [ ! -f .env ]; then
        echo "==> .env missing, copying .env.example"
        cp .env.example .env
    fi

    if ! grep -q '^APP_KEY=base64:' .env; then
        echo "==> generating APP_KEY"
        php artisan key:generate --force --no-interaction
    fi
fi

# Fail loudly at boot rather than serving 500s: with APP_DEBUG=false a missing
# APP_KEY looks like a generic Internal Server Error on every request.
if [ "$APP_ENV" = "production" ] && [ ! -f .env ] && [ -z "$APP_KEY" ]; then
    echo "FATAL: APP_KEY is not set." >&2
    echo "       Generate one and put it in .env.production:" >&2
    echo "       docker run --rm php:8.5-cli php -r \"echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;\"" >&2
    exit 1
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
