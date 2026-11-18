#!/bin/sh
set -e

PORT="${PORT:-8080}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

if [ -z "${DB_URL}" ] && [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    export DB_CONNECTION=sqlite
    export DB_DATABASE=/var/www/html/database/database.sqlite
    touch "$DB_DATABASE"
    chown www-data:www-data "$DB_DATABASE" /var/www/html/database
fi

php artisan config:cache
php artisan route:cache
php artisan migrate --force

if [ "${SEED_IF_EMPTY:-false}" = "true" ] && [ "$(php artisan tinker --execute='echo \App\Models\User::count();')" = "0" ]; then
    php artisan db:seed --force
fi

exec apache2-foreground
