# syntax=docker/dockerfile:1
# Production image: FrankenPHP (Caddy + PHP) running Symfony in worker mode.

FROM dunglas/frankenphp:1-php8.5 AS app

# intl for NumberFormatter, pdo_pgsql for the default Postgres setup (pdo_sqlite is built in), zip for composer.
RUN install-php-extensions \
    intl \
    pdo_pgsql \
    apcu \
    opcache \
    zip

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/app.ini "$PHP_INI_DIR/conf.d/app.ini"

ENV APP_ENV=prod APP_DEBUG=0
# Plain HTTP by default; set SERVER_NAME to a real hostname for automatic TLS.
ENV SERVER_NAME=":80"
# Worker mode (boot once, serve many); unset FRANKENPHP_CONFIG for classic per-request mode.
ENV FRANKENPHP_CONFIG="worker ./public/index.php"
ENV APP_RUNTIME="Runtime\\FrankenPhpSymfony\\Runtime"

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Install PHP dependencies first so this layer is cached across code changes.
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .

# post-install-cmd runs importmap:install, which downloads the gitignored assets/vendor JS.
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && composer run-script --no-dev post-install-cmd \
    && php bin/console asset-map:compile \
    && php bin/console cache:warmup

COPY --chmod=755 docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

# Non-root: setcap lets www-data bind 80/443; Caddy state and var/ (cache is recompiled on boot) must stay writable.
RUN setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && chown -R www-data:www-data /data /config /app/var
USER www-data

EXPOSE 80 443 443/udp

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
