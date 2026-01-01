FROM php:8.4-fpm

ARG APP_ENV=dev
ENV APP_ENV=$APP_ENV

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

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.log=/tmp/xdebug.log" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.idekey=PHPSTORM" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html/v2ray/src

COPY ./src/composer.json ./src/composer.lock* ./
COPY ./src ./

RUN if [ "$APP_ENV" = "prod" ]; then \
        export XDEBUG_MODE=off; \
        composer install --no-interaction --optimize-autoloader --no-dev --no-scripts; \
        rm /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini; \
    else \
        export XDEBUG_MODE=off; \
        composer install --no-interaction --optimize-autoloader; \
    fi

RUN chown -R www-data:www-data /var/www/html/v2ray/src \
    && chmod -R 775 /var/www/html/v2ray/src/storage /var/www/html/v2ray/src/bootstrap/cache

EXPOSE 9000

CMD sh -c "if [ ! -f /var/www/html/v2ray/src/.env ]; then cp /var/www/html/v2ray/src/.env.example /var/www/html/v2ray/src/.env; fi && php-fpm"
