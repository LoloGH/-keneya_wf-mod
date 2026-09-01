# PHP-FPM 8.4 pour KEneYa WorkFlow.
#
# 8.4 et non 8.3 : le composer.lock committe verrouille symfony/clock v8.1, qui
# exige php >= 8.4.1. Sur 8.3, le `composer install` ci-dessous echoue et
# l'image ne se construit pas — donc ni `app`, ni `scheduler`, ni
# `queue-worker`. Le serveur de production tourne deja en php8.4-fpm.
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        git \
        icu-dev \
        libzip-dev \
        mysql-client \
        oniguruma-dev \
        tzdata \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/keneya.ini

WORKDIR /var/www/html

# Les dependances sont installees d'abord, pour profiter du cache de couches
# lorsque seul le code applicatif change.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
