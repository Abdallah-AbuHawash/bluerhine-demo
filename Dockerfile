# --- base: the development image (source is bind-mounted over it) -----------
FROM php:8.5-fpm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath zip gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies are baked into the image so a clean clone (no vendor/) boots.
# The entrypoint re-installs them into the bind mount when it is empty.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist \
    && mv vendor /opt/vendor

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# --- assets: build the frontend once, at image build time -------------------
FROM node:22-alpine AS assets

WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

# --- prod: self-contained image, no bind mounts, no dev server --------------
FROM base AS prod

ENV APP_ENV=production \
    APP_DEBUG=false

COPY . /var/www/html
COPY --from=assets /build/public/build /var/www/html/public/build

RUN composer install --no-interaction --no-dev --optimize-autoloader --prefer-dist \
    && rm -rf /var/www/html/public/hot \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# --- web: nginx with the built public/ baked in (no shared bind mounts) -----
FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=prod /var/www/html/public /var/www/html/public
