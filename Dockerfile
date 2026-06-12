#syntax=docker/dockerfile:1

# Adapted from https://github.com/dunglas/symfony-docker (without Mercure/Vulcain).

# Versions
FROM dunglas/frankenphp:1-php8.5 AS frankenphp_upstream

# The different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/build/building/multi-stage/#stop-at-a-specific-build-stage
# https://docs.docker.com/reference/compose-file/build/#target


# Base FrankenPHP image
FROM frankenphp_upstream AS frankenphp_base

SHELL ["/bin/bash", "-euxo", "pipefail", "-c"]

WORKDIR /app

# pdo_sqlite is built in; pdo_pgsql and pdo_mysql cover the other DATABASE_URL choices.
RUN install-php-extensions \
	@composer \
	apcu \
	intl \
	opcache \
	pdo_mysql \
	pdo_pgsql \
	zip

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

# Worker mode is configured in the Caddyfile; the runtime degrades gracefully for CLI commands.
ENV APP_RUNTIME="Runtime\\FrankenPhpSymfony\\Runtime"

COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/frankenphp/Caddyfile

ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=60s CMD php -r 'exit(false === @file_get_contents("http://localhost:2019/metrics", context: stream_context_create(["http" => ["timeout" => 5]])) ? 1 : 0);'
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]


# Dev FrankenPHP image
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev
ENV XDEBUG_MODE=off
ENV FRANKENPHP_WORKER_CONFIG=watch

RUN <<-EOF
	mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
	install-php-extensions xdebug
EOF

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch" ]


# Prod FrankenPHP image
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/

# Prevent the reinstallation of vendors at every change in the source code
COPY --link composer.* symfony.* ./
RUN composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

# Copy sources
COPY --link --exclude=frankenphp/ . ./

# post-install-cmd runs importmap:install, which downloads the gitignored assets/vendor JS.
RUN <<-EOF
	mkdir -p var/cache var/log
	composer dump-autoload --classmap-authoritative --no-dev
	composer dump-env prod
	composer run-script --no-dev post-install-cmd
	php bin/console asset-map:compile
	chmod +x bin/console
	sync
EOF

# Non-root: setcap lets www-data bind 80/443 (Docker allows unprivileged low ports, Kubernetes
# does not by default); Caddy state and var/ (cache is recompiled on boot) must stay writable.
RUN setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
	&& chown -R www-data:www-data /data /config /app/var
USER www-data

EXPOSE 80 443 443/udp
