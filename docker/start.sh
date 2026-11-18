#!/bin/sh
set -e

PORT="${PORT:-8080}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan config:cache
php artisan route:cache
php artisan migrate --force

if [ "${SEED_IF_EMPTY:-false}" = "true" ] && [ "$(php artisan tinker --execute='echo \App\Models\User::count();')" = "0" ]; then
    php artisan db:seed --force
fi

exec apache2-foreground
