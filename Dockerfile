FROM php:8.4-fpm

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

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html/v2ray/src

COPY ./src/composer.json ./src/composer.lock* ./

RUN composer install --no-interaction --optimize-autoloader --no-dev --no-scripts

COPY ./src .

RUN chown -R www-data:www-data /var/www/html/v2ray/src \
    && chmod -R 775 /var/www/html/v2ray/src/storage /var/www/html/v2ray/src/bootstrap/cache

EXPOSE 9000

CMD sh -c "\
    if [ ! -f /var/www/html/v2ray/src/.env ]; then cp /var/www/html/v2ray/src/.env.example /var/www/html/v2ray/src/.env; fi && \
    php-fpm"
