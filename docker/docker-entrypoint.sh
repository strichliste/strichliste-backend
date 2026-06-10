#!/bin/sh
set -e

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
