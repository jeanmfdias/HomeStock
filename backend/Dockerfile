# Multi-stage production image: build Vue assets, then bundle into a
# php-fpm + nginx single container running via supervisord.

FROM node:22-alpine AS frontend-build
WORKDIR /frontend
COPY frontend/package.json frontend/package-lock.json* ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

FROM php:8.5-fpm-alpine AS backend-build
RUN apk add --no-cache \
        git unzip icu-dev sqlite-dev \
    && docker-php-ext-install bcmath intl pdo_sqlite

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-progress --no-interaction --optimize-autoloader

COPY backend/ ./
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && php bin/console cache:clear --env=prod --no-debug \
    && php bin/console cache:warmup --env=prod --no-debug

FROM php:8.5-fpm-alpine
RUN apk add --no-cache \
        nginx supervisor sqlite icu-libs \
    && docker-php-ext-install bcmath intl pdo_sqlite

RUN { \
        echo "opcache.enable=1"; \
        echo "opcache.enable_cli=0"; \
        echo "opcache.memory_consumption=192"; \
        echo "opcache.max_accelerated_files=20000"; \
        echo "opcache.validate_timestamps=0"; \
    } > /usr/local/etc/php/conf.d/opcache-prod.ini

WORKDIR /app
COPY --from=backend-build /app /app
COPY --from=frontend-build /frontend/dist /app/public/app
COPY docker/nginx/prod.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY backend/docker-entrypoint.sh /usr/local/bin/homestock-entrypoint

RUN mkdir -p /app/var/data /app/var/cache /app/var/log /run/nginx \
    && chown -R www-data:www-data /app/var \
    && chmod +x /usr/local/bin/homestock-entrypoint

EXPOSE 80
ENTRYPOINT ["homestock-entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf", "-n"]
