#!/bin/sh
set -e

# The committed .env secret is fine for dev but publicly known — refuse to be
# silent about it in a container that may face a network.
if [ -z "${APP_SECRET:-}" ] || [ "${APP_SECRET:-}" = "afcb8ed6bf80cf0d8d9196390e06a408" ]; then
    echo 'WARNING: APP_SECRET is unset or the publicly-known development default.' >&2
    echo '         Set a unique APP_SECRET for any real deployment.' >&2
fi

# Recompile the container cache on boot: settings (config/strichliste.yaml)
# are compiled into the DI container, so the build-time warmup would silently
# ignore a bind-mounted config or changed environment.
php bin/console cache:clear >/dev/null

# Wait for the database and apply pending migrations before serving traffic.
# Disable with AUTO_MIGRATE=0 (e.g. when running several replicas).
if [ "${AUTO_MIGRATE:-1}" = "1" ]; then
    tries=0
    until php bin/console dbal:run-sql 'SELECT 1' >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [ "$tries" -gt 30 ]; then
            echo 'Database is unreachable after 60s, giving up.' >&2
            exit 1
        fi
        echo 'Waiting for the database...'
        sleep 2
    done
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

exec "$@"
