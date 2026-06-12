#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	# Dev: a freshly cloned project bind-mounted into the container has no vendor/ yet.
	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	if [ "${APP_ENV:-prod}" != 'dev' ]; then
		# The committed .env secret is publicly known — don't let it face a network silently.
		if [ -z "${APP_SECRET:-}" ] || [ "${APP_SECRET:-}" = 'afcb8ed6bf80cf0d8d9196390e06a408' ]; then
			echo 'WARNING: APP_SECRET is unset or the publicly-known development default.' >&2
			echo '         Set a unique APP_SECRET for any real deployment.' >&2
		fi

		# config/strichliste.yaml is compiled into the container cache — recompile
		# so a bind-mounted copy is honored.
		php bin/console cache:clear >/dev/null
	fi

	php bin/console -V

	echo 'Waiting for the database to be ready...'
	ATTEMPTS_LEFT_TO_REACH_DATABASE=60
	until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php bin/console dbal:run-sql -q "SELECT 1" 2>&1); do
		if [ $? -eq 255 ]; then
			# An exit status of 255 marks an unrecoverable error (bad DSN, missing driver, ...)
			ATTEMPTS_LEFT_TO_REACH_DATABASE=0
			break
		fi
		sleep 1
		ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
		echo "Still waiting for the database... $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
	done

	if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
		echo 'The database is not up or not reachable:' >&2
		echo "$DATABASE_ERROR" >&2
		echo 'Check DATABASE_URL — and if you expected the bundled Postgres, COMPOSE_PROFILES=database in .env.' >&2
		exit 1
	fi
	echo 'The database is now ready and reachable'

	if [ "$(find ./migrations -iname '*.php' -print -quit)" ]; then
		php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
	fi

	echo 'PHP app ready!'
fi

exec docker-php-entrypoint "$@"
