# Базовый образ PHP-FPM
FROM php:8.4-fpm

# Аргумент сборки и переменная окружения
ARG APP_ENV=dev
ENV APP_ENV=$APP_ENV

# Устанавливаем зависимости и расширения PHP
RUN apt-get update && apt-get install -y \
    default-mysql-client \
    libonig-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    libxml2-dev \
    pkg-config \
    zlib1g-dev \
    libyaml-dev \
    && docker-php-ext-install pdo_mysql mbstring bcmath pcntl sockets zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.log=/tmp/xdebug.log" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.idekey=PHPSTORM" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Рабочая директория
WORKDIR /var/www/html/v2ray/src

# Копируем файлы проекта
COPY ./src/composer.json ./src/composer.lock* ./
COPY ./src ./

# Устанавливаем зависимости Composer (как root)
# Для production используем --no-dev, для dev устанавливаем все зависимости
RUN if [ "$APP_ENV" = "prod" ]; then \
        composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev; \
    else \
        composer install --no-interaction --prefer-dist --optimize-autoloader; \
    fi

# Устанавливаем права на storage и bootstrap/cache
RUN chown -R www-data:www-data /var/www/html/v2ray/src \
    && find /var/www/html/v2ray/src/storage -type d -exec chmod 775 {} \; \
    && find /var/www/html/v2ray/src/storage -type f -exec chmod 664 {} \; \
    && find /var/www/html/v2ray/src/bootstrap/cache -type d -exec chmod 775 {} \; \
    && find /var/www/html/v2ray/src/bootstrap/cache -type f -exec chmod 664 {} \;

# Переключаемся на пользователя php-fpm
USER www-data

# Экспорт порта php-fpm
EXPOSE 9000

# Команда запуска PHP-FPM
# Примечание: .env создается в CI/CD скрипте перед запуском контейнера
CMD ["php-fpm"]
