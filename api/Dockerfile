# syntax=docker/dockerfile:1.7
# Build context: HQ/ (repo root)
# Build:  docker build -f api/Dockerfile -t hq-api .

# ----- Stage 1: vendor build -----
FROM composer:2.9 AS vendor
WORKDIR /app
COPY api/composer.json api/composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --no-autoloader \
        --prefer-dist
COPY api/ ./
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ----- Stage 2: runtime -----
FROM php:8.4-fpm-alpine AS runtime

ARG SERVICE=api
ENV APP_SERVICE=${SERVICE}

RUN apk add --no-cache \
        nginx \
        supervisor \
        curl \
        icu-libs \
        oniguruma \
        libzip \
    # hadolint ignore=SC2086
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        intl \
        bcmath \
        opcache \
        zip \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/*

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /app /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/nginx/api.conf /etc/nginx/http.d/default.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /var/www/html/storage/framework/sessions \
                /var/www/html/storage/framework/views \
                /var/www/html/storage/framework/cache \
                /var/www/html/storage/logs \
                /var/www/html/bootstrap/cache \
                /run/nginx /var/lib/nginx/tmp /var/log/supervisor \
    && chown -R www-data:www-data \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache \
        /run/nginx \
        /var/lib/nginx \
        /var/log/supervisor \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=3s --start-period=15s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/healthz || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf", "-n"]
