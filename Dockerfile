# ReferralFlow staging image. NOT built or run on the development machine (no Docker available there).
# Synthetic data only. See docs/DEPLOYMENT.md.
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.4-fpm-alpine AS app
RUN apk add --no-cache libpq libpng libjpeg-turbo libsodium \
 && apk add --no-cache --virtual .build postgresql-dev libpng-dev libjpeg-turbo-dev libsodium-dev \
 && docker-php-ext-configure gd --with-jpeg \
 && docker-php-ext-install pdo_pgsql pdo_mysql gd sodium opcache pcntl \
 && apk del .build
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/private bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && php artisan package:discover --ansi
ENV APP_ENV=production APP_DEBUG=false
# The app refuses to boot in production mode without REFERRALFLOW_PRODUCTION_ACK (docs/GO-LIVE-GATE.md).
# For a STAGING container leave APP_ENV=staging and REFERRALFLOW_MODE=staging.
USER www-data
CMD ["php-fpm"]
