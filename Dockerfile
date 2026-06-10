# syntax=docker/dockerfile:1
# Production image: FrankenPHP (Caddy + PHP) running Symfony in worker mode.
# Build:  docker build -t strichliste .
# Run:    docker compose up  (see compose.yaml)

FROM dunglas/frankenphp:1-php8.5 AS app

# intl       -> NumberFormatter (currency display)
# pdo_pgsql  -> default Postgres setup (pdo_sqlite is built in)
# apcu       -> fast local cache backend
# zip        -> composer dist downloads during the build
RUN install-php-extensions \
    intl \
    pdo_pgsql \
    apcu \
    opcache \
    zip

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/app.ini "$PHP_INI_DIR/conf.d/app.ini"

ENV APP_ENV=prod APP_DEBUG=0
# Plain HTTP on :80. Set SERVER_NAME to a real hostname to let Caddy obtain
# TLS certificates automatically (and publish port 443).
ENV SERVER_NAME=":80"
# Run Symfony as a long-lived FrankenPHP worker (boot once, serve many).
# Unset FRANKENPHP_CONFIG to fall back to classic one-process-per-request mode.
ENV FRANKENPHP_CONFIG="worker ./public/index.php"
ENV APP_RUNTIME="Runtime\\FrankenPhpSymfony\\Runtime"

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Install PHP dependencies first so this layer is cached across code changes.
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .

# Autoloader + Flex post-install scripts (cache:clear, assets:install,
# importmap:install — downloads the gitignored assets/vendor JS), then
# pre-compile the asset-mapper files and warm the prod cache.
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && composer run-script --no-dev post-install-cmd \
    && php bin/console asset-map:compile \
    && php bin/console cache:warmup

COPY --chmod=755 docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
# Port-aware HTTP->HTTPS redirect, enabled via TLS_REDIRECT_HOSTS (see file).
COPY docker/redirect.caddyfile /etc/frankenphp/Caddyfile.d/redirect.caddyfile

EXPOSE 80 443

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
