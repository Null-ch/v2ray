#!/bin/sh
set -e

# Устанавливаем права на storage и bootstrap/cache
chown -R www-data:www-data /var/www/html/v2ray/src/storage /var/www/html/v2ray/src/bootstrap/cache
chmod -R 775 /var/www/html/v2ray/src/storage /var/www/html/v2ray/src/bootstrap/cache

# Генерация .env если нет
if [ ! -f /var/www/html/v2ray/src/.env ]; then
    cp /var/www/html/v2ray/src/.env.example /var/www/html/v2ray/src/.env
fi

# Генерация APP_KEY если нужно
if ! grep -q '^APP_KEY=' /var/www/html/v2ray/src/.env || grep -q '^APP_KEY=$' /var/www/html/v2ray/src/.env; then
    php artisan key:generate --force
fi

# Запускаем PHP-FPM
exec php-fpm