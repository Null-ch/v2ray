#!/bin/sh
set -e

# Устанавливаем права на storage и bootstrap/cache.
# Контейнер запущен от `www-data` (см. Dockerfile), поэтому `chown` может быть запрещен.
# Если мы не root — пропускаем `chown`, чтобы не валить контейнер.
if [ "$(id -u)" -eq 0 ]; then
  chown -R www-data:www-data /var/www/html/v2ray/src/storage /var/www/html/v2ray/src/bootstrap/cache
fi

# chmod может тоже быть запрещён в некоторых bind-mount сценариях (Windows->Linux),
# поэтому не останавливаемся на ошибке.
chmod -R 775 /var/www/html/v2ray/src/storage /var/www/html/v2ray/src/bootstrap/cache || true

# Генерация .env если нет
if [ ! -f /var/www/html/v2ray/src/.env ]; then
    cp /var/www/html/v2ray/src/.env.example /var/www/html/v2ray/src/.env
fi

# Если токен передан в переменных окружения (docker-compose), подставляем его в .env,
# чтобы Laravel гарантированно видел корректное значение.
if [ -n "${TELEGRAM_BOT_TOKEN:-}" ]; then
  if grep -q '^TELEGRAM_BOT_TOKEN=' /var/www/html/v2ray/src/.env; then
    sed -i 's|^TELEGRAM_BOT_TOKEN=.*|TELEGRAM_BOT_TOKEN='"$TELEGRAM_BOT_TOKEN"'|g' /var/www/html/v2ray/src/.env
  else
    echo "TELEGRAM_BOT_TOKEN=${TELEGRAM_BOT_TOKEN}" >> /var/www/html/v2ray/src/.env
  fi
fi

# Генерация APP_KEY если нужно
if ! grep -q '^APP_KEY=' /var/www/html/v2ray/src/.env || grep -q '^APP_KEY=$' /var/www/html/v2ray/src/.env; then
    php artisan key:generate --force
fi

# Запускаем PHP-FPM
#
# Важно: в docker-compose для сервисов `app`/`web`/`bot`/`queue` может быть задана
# своя команда. Поэтому если команда передана, запускаем ее; иначе поднимаем php-fpm.
if [ "$#" -gt 0 ]; then
  # Скрипты могут быть смонтированы с хоста (например, Windows),
  # и тогда бит executable может быть потерян. Для docker-queue.sh запускаем через `sh`.
  if [ "$1" = "/docker-queue.sh" ]; then
    exec sh /docker-queue.sh
  fi

  exec "$@"
fi

exec php-fpm