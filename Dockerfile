FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress \
    --optimize-autoloader --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction

FROM php:8.3-fpm-alpine AS runtime
RUN apk add --no-cache icu-libs libzip mysql-client \
    && apk add --no-cache --virtual .build-deps icu-dev libzip-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" bcmath intl opcache pcntl pdo_mysql zip \
    && apk del .build-deps

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app ./
COPY docker/php.ini /usr/local/etc/php/conf.d/99-ahmed-lms.ini

RUN mkdir -p storage/app/private storage/app/public storage/framework/cache \
    storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 9000
CMD ["php-fpm", "-F"]
