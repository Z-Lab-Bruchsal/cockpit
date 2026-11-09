#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

# SQLite needs write access to the file and its directory (journal files).
# Without DB_DATABASE Laravel uses database/database.sqlite.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ "$DB_DATABASE" != ":memory:" ]; then
    DB_FILE="${DB_DATABASE:-database/database.sqlite}"
    mkdir -p "$(dirname "$DB_FILE")"
    touch -a "$DB_FILE"
    chown www-data:www-data "$DB_FILE" "$(dirname "$DB_FILE")"
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
