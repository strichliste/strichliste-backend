#!/bin/sh
set -e

if [ ! -f /source/var/app.db ]; then
    echo "Initializing database"
    cp /source/app.db.example /source/var/app.db
fi

exec "$@"