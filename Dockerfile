# syntax=docker/dockerfile:1.7
ARG PHP_VERSION=8.5

# --- frontend assets -------------------------------------------------------
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# --- application -------------------------------------------------------
FROM php:${PHP_VERSION}-apache AS app

RUN apt-get update && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
        libicu-dev \
        libzip-dev \
        unzip \
        git \
        curl \
    && docker-php-ext-install \
        pdo_sqlite \
        intl \
        zip \
        bcmath \
        pcntl \
        opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

COPY . .
COPY --from=frontend /app/public/build ./public/build

# auth.json holds the Filament composer repository credentials; it's
# gitignored and must never end up baked into an image layer, so it's
# supplied as a BuildKit secret and mounted only for this RUN step.
RUN --mount=type=secret,id=composer_auth,target=/var/www/html/auth.json \
    composer install --no-dev --no-interaction --no-progress --optimize-autoloader

RUN mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
