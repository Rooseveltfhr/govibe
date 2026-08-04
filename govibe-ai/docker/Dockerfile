# syntax=docker/dockerfile:1

########################################
# Base PHP commune (dev + prod)
########################################
FROM php:8.4-cli-alpine AS base

RUN apk add --no-cache \
        git unzip icu-dev libzip-dev postgresql-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql intl zip bcmath pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

########################################
# Dev : code monté en volume, artisan serve
########################################
FROM base AS dev

ENV COMPOSER_ALLOW_SUPERUSER=1

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

########################################
# Vendor de prod (sans dev-deps)
########################################
FROM base AS vendor

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

########################################
# Prod : image applicative autonome
# (web, worker et scheduler partagent cette image ; la commande diffère)
########################################
FROM base AS prod

ENV APP_ENV=production APP_DEBUG=false

COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor

RUN composer dump-autoload --optimize --no-dev \
    && php artisan storage:link || true

EXPOSE 8000

# web:       php artisan serve (Octane/FrankenPHP prévu en Phase 8 d'échelle)
# worker:    php artisan horizon (dès que Horizon est installé — Phase 1)
# scheduler: php artisan schedule:work
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
