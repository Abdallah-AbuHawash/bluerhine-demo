#!/bin/sh
set -e

cd /var/www/html

# Clean clone: vendor/ is gitignored, so seed it from the image copy.
if [ ! -f vendor/autoload.php ]; then
    echo "==> vendor/ missing, installing dependencies"
    cp -R /opt/vendor vendor
    composer dump-autoload --optimize --no-interaction
fi

if [ ! -f .env ]; then
    echo "==> .env missing, copying .env.example"
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "==> generating APP_KEY"
    php artisan key:generate --force --no-interaction
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R a+rw storage bootstrap/cache

exec "$@"
