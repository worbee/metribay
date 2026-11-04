# Metribay standalone host: one image, three roles (web, worker, scheduler).
# See docs/DOCKER.md. Build from the repository root: docker build -t metribay .
FROM dunglas/frankenphp:1-php8.5-alpine

RUN install-php-extensions bcmath intl pcntl pdo_pgsql zip \
    && apk add --no-cache sqlite tzdata
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .
WORKDIR /app/standalone
# Everything the application writes lives in the /var/lib/metribay volume.
RUN rm -rf storage && ln -s /var/lib/metribay/storage storage \
    && mkdir -p bootstrap/cache /var/lib/metribay/storage/framework/views /var/lib/metribay/storage/framework/cache/data \
        /var/lib/metribay/storage/framework/sessions /var/lib/metribay/storage/logs /var/lib/metribay/storage/app/private \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --prefer-dist --classmap-authoritative \
    && php artisan vendor:publish --tag=metribay-assets --force \
    && rm -rf bootstrap/cache/*.php /var/lib/metribay/storage/framework/views/* /root/.composer \
    && chown -R www-data:www-data /var/lib/metribay bootstrap/cache /data /config \
    && chmod -R a+rX public

COPY docker/php.ini /usr/local/etc/php/conf.d/99-metribay.ini
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/metribay

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/lib/metribay/metribay.sqlite \
    DB_BUSY_TIMEOUT=10000 \
    DB_JOURNAL_MODE=wal \
    METRIBAY_EDITION=personal \
    SESSION_COOKIE=metribay_personal_session

USER www-data
VOLUME /var/lib/metribay
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s CMD ["metribay", "healthcheck"]
ENTRYPOINT ["metribay"]
CMD ["web"]
