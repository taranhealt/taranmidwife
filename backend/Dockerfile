FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --optimize-autoloader --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm install --ignore-scripts --no-audit --no-fund
RUN npm run build

FROM php:8.4-fpm-alpine AS runtime
RUN apk add --no-cache nginx curl icu-libs libzip libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" bcmath intl opcache pcntl pdo_pgsql zip \
    && apk del .build-deps
WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/entrypoint.sh /usr/local/bin/taran-entrypoint
RUN chmod 0755 /usr/local/bin/taran-entrypoint \
    && mkdir -p /run/nginx /var/www/html/storage/framework/cache /var/www/html/storage/framework/sessions /var/www/html/storage/framework/views /var/www/html/storage/logs /var/www/html/storage/app/private /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
EXPOSE 9000 8080
ENTRYPOINT ["taran-entrypoint"]
CMD ["php-fpm", "-F"]
