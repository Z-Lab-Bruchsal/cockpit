#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

if [ -n "$DB_DATABASE" ] && [ "$DB_CONNECTION" = "sqlite" ]; then
    mkdir -p "$(dirname "$DB_DATABASE")"
    touch -a "$DB_DATABASE"
    chown www-data:www-data "$DB_DATABASE"
fi

# Only the web process runs bootstrap steps, so the queue/scheduler
# containers (which override CMD) don't race each other on migrations.
if [ "$1" = "apache2-foreground" ]; then
    php artisan config:clear
    php artisan migrate --force
    php artisan storage:link || true
fi

# After the artisan calls above, so files they create as root (e.g. the
# log file) remain writable for Apache.
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
