#!/bin/sh
set -e

if [ ! -f /var/www/html/v2ray/src/.env ]; then
    cp /var/www/html/v2ray/src/.env.example /var/www/html/v2ray/src/.env
fi

if ! grep -q '^APP_ENV=' /var/www/html/v2ray/src/.env; then
    echo "APP_ENV=${APP_ENV}" >> /var/www/html/v2ray/src/.env
fi

if ! grep -q '^APP_KEY=' /var/www/html/v2ray/src/.env || grep -q '^APP_KEY=$' /var/www/html/v2ray/src/.env; then
    php artisan key:generate --force
fi

exec php artisan queue:work --sleep=3 --tries=3 --timeout=90
EOF

sudo chmod +x docker-queue.sh