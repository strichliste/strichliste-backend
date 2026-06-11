#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	# The committed .env secret is publicly known — don't let it face a network silently.
	if [ -z "${APP_SECRET:-}" ] || [ "${APP_SECRET:-}" = "afcb8ed6bf80cf0d8d9196390e06a408" ]; then
		echo 'WARNING: APP_SECRET is unset or the publicly-known development default.' >&2
		echo '         Set a unique APP_SECRET for any real deployment.' >&2
	fi

	# strichliste.yaml is compiled into the container — recompile so a bind-mounted copy is honored.
	php bin/console cache:clear >/dev/null

	if grep -q ^DATABASE_URL= .env; then
		echo 'Waiting for database to be ready...'
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php bin/console dbal:run-sql -q "SELECT 1" 2>&1); do
			if [ $? -eq 255 ]; then
				# If the Doctrine command exits with 255, an unrecoverable error occurred
				ATTEMPTS_LEFT_TO_REACH_DATABASE=0
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for database to be ready... Or maybe the database is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo 'The database is not up or not reachable:'
			echo "$DATABASE_ERROR"
			exit 1
		else
			echo 'The database is now ready and reachable'
		fi

		if [ "$(find ./migrations -iname '*.php' -print -quit)" ]; then
			php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
		fi
	fi

	echo 'PHP app ready!'
fi

exec docker-php-entrypoint "$@"
