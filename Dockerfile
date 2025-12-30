FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    default-mysql-client \
    libonig-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-install mbstring \
    && docker-php-ext-install bcmath \
    && docker-php-ext-install pcntl \
    && docker-php-ext-install sockets

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
