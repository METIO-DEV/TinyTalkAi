FROM php:8.4-fpm-bookworm

ARG UID=1000
ARG GID=1000

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libpq-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupmod -g "${GID}" www-data \
    && usermod -u "${UID}" -g "${GID}" www-data

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

USER www-data
