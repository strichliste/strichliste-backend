# strichliste

strichliste ([ʃtʀɪçˈlɪstə], German word for tally sheet) is a tool to replace a tally sheet inside a hackerspace.

## Architecture

This repository is a **single Symfony 7.4 application** that serves:

- **The UI** as server-rendered Twig pages at the real user-facing routes
  (`/`, `/user/active`, `/user/{id}`, `/articles/*`, `/split-invoice`, `/metrics`,
  `/search-results`, `/settings`). The UI is the **primary** way to use
  the application — it is operable without JavaScript (kiosk-grade
  progressive enhancement via Symfony UX: Turbo, Stimulus, twig-component).
- **The legacy REST API** at `/api/*` for any external client
  (Android apps, third-party kiosk software, etc.). The API contract is
  **frozen** at the shape it had before the Twig UI shipped.
  Interactive OpenAPI documentation is served at **`/api/doc`**
  (Swagger UI; raw spec at `/api/doc.json`), maintained as `#[OA\*]`
  attributes on `src/Controller/Api/*` and the schema carriers in
  `src/ApiDoc/*`.

The previous standalone React SPA (`strichliste-web-frontend/`) has been
removed; its README and roadmap are preserved under `../specs/_archive/`
for historical reference.

## Run with Docker (recommended)

The container setup follows
[dunglas/symfony-docker](https://github.com/dunglas/symfony-docker):
[FrankenPHP](https://frankenphp.dev/) (PHP 8.5, Caddy) running Symfony
in **worker mode** — the app boots once and stays resident — plus
Postgres 16. There is one `Dockerfile` with a dev and a prod stage, and
three compose files:

| File | Role |
| --- | --- |
| `compose.yaml` | Base: `app` (FrankenPHP) + `database` (Postgres). |
| `compose.override.yaml` | Dev override, picked up automatically: source bind-mounted, worker restarts on file changes, Xdebug available, Postgres reachable from the host on `127.0.0.1:5433`. |
| `compose.prod.yaml` | Prod override: baked image, compiled assets and warmed cache. |

Requires a reasonably current Docker (Engine 25+ with Compose v2.30+ —
any recent Docker Desktop or `docker-ce` qualifies).

### Development

```
make up
```

(equivalent to `docker compose up -d --build --wait`, but with a generous
first-boot timeout — the initial build downloads dependencies, which can
outlast the default wait). No `make`? Run the `docker compose` line and
add `--wait-timeout 300`.

Open **`https://localhost`**. Caddy serves TLS out of the box using its
local CA, so browsers (which try HTTPS first these days) connect
directly; plain `http://localhost` is redirected. On the first visit
your browser shows a certificate warning — accept it once, or trust the
CA root permanently (works on Linux, macOS and Windows):

```
make tls
```

The CA lives in the `caddy_data` volume, so the trust survives
container rebuilds (Windows: run `make tls` from cmd.exe). The
entrypoint waits for the database, installs `vendor/` if missing and
applies migrations before serving traffic; code changes restart the
worker automatically (`watch` mode). To step-debug, start with
`XDEBUG_MODE=debug docker compose up -d`. `make help` lists the
shortcuts (`make up`, `make logs`, `make test`, ...).

On Linux hosts note that the dev container runs as root, so files it
creates on the bind mount (`vendor/`, `assets/vendor/`) end up
root-owned — same trade-off as upstream symfony-docker. Run
`make fix-perms` to hand them back to your user.

### Production

Set a real `APP_SECRET` in `.env` (the committed value is publicly
known; the container warns loudly if you keep it), then:

```
docker compose -f compose.yaml -f compose.prod.yaml up -d --build --wait
```

or `make prod`. The prod image bakes the code, compiled assets and a
warmed cache; nothing is bind-mounted. Migrations still run on boot.

Two production guardrails:

- Uncomment `COMPOSE_FILE=compose.yaml:compose.prod.yaml` in `.env` on
  the production host. Without it, a plain `docker compose up -d` weeks
  later silently loads the *development* override — bind mount, dev
  image, and an empty anonymous `var/` volume that makes a SQLite setup
  look like all balances vanished.
- The prod image bakes `.env` into it (`composer dump-env`). Fine for
  an image built and run on the same box — but don't push an image to a
  registry with real secrets in `.env`; pass those at runtime instead.

If host ports 80/443 are already taken, remap them in `.env`
(`HTTP_PORT=8080`, `HTTPS_PORT=8443`, `HTTP3_PORT=8443`) and open
`https://localhost:8443` directly (the HTTP→HTTPS redirect targets the
standard port, so skip the HTTP URL when remapping).

### Choosing the database (.env)

Everything database-related is driven by `.env` — no compose editing:

- **Default**: the bundled Postgres service (`COMPOSE_PROFILES=database`
  in `.env` keeps it enabled). Credentials and version are tunable via
  `POSTGRES_USER` / `POSTGRES_PASSWORD` / `POSTGRES_DB` /
  `POSTGRES_VERSION` (note: Postgres only applies the password on the
  very first start, before the `database_data` volume exists).
- **Anything else**: set `DATABASE_URL` to any Doctrine DSN and switch
  the bundled Postgres off by setting `COMPOSE_PROFILES=` (empty). The
  image ships the SQLite, MySQL/MariaDB and Postgres drivers:

  ```dotenv
  # single-container SQLite (stored in the app_var volume)
  DATABASE_URL="sqlite:////app/var/data.db"
  COMPOSE_PROFILES=

  # external MariaDB
  DATABASE_URL="mysql://user:pass@192.168.1.10:3306/strichliste?serverVersion=10.11.2-MariaDB&charset=utf8mb4"
  COMPOSE_PROFILES=
  ```

Other knobs (all in `.env`, see the comments there):

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_SECRET` | dev value (publicly known!) | Set a unique secret for any real deployment. |
| `SERVER_NAME` | `localhost` (self-signed TLS) | Set a real hostname (e.g. `strichliste.example.com`) for automatic Let's Encrypt certificates, or `":80"` for plain HTTP only (e.g. LAN-IP kiosks). |
| `HTTP_PORT` / `HTTPS_PORT` / `HTTP3_PORT` | `80` / `443` / `443` | Published host ports. |

What the containers do for you:

- **Migrations on boot** — the entrypoint waits for the database and
  applies pending migrations before serving traffic.
- **Settings without rebuilding** — bind-mount your own settings file
  into the `app` service; the entrypoint recompiles the container cache
  on boot so it is picked up. Put the snippet in a **new** file, e.g.
  `compose.custom.yaml` (not `compose.override.yaml` — that one is the
  development override and is not loaded in production), and extend the
  `COMPOSE_FILE` pin in `.env` to
  `compose.yaml:compose.prod.yaml:compose.custom.yaml`:

  ```yaml
  # compose.custom.yaml
  services:
    app:
      volumes:
        - ./config/strichliste.yaml:/app/config/strichliste.yaml:ro
  ```

- **Health checks, restart policies and log rotation** are
  preconfigured; `--wait` blocks until the app actually serves traffic.
  If `up --wait` hangs or fails, `docker compose logs app` shows
  exactly what the entrypoint is waiting for.

Single container without compose (generate the secret **once** and keep
it — don't regenerate it on every run):

```
docker build --target frankenphp_prod -t strichliste .
echo "APP_SECRET=$(openssl rand -hex 32)" > strichliste.env   # keep this file
docker run -d --restart unless-stopped \
  -p 80:80 -p 443:443 -p 443:443/udp \
  -e SERVER_NAME=localhost \
  -e DATABASE_URL="sqlite:////app/var/data.db" \
  --env-file strichliste.env \
  -v strichliste-data:/app/var \
  strichliste
```

### Reusing an existing database

Already running strichliste and want to keep your data? You do **not**
need an import step — point the app at the old database and start it.
The schema migrations are written to be safe to run on a populated
database (they detect an existing schema and skip it), so the entrypoint
brings an old database up to the current version on first boot.

- **An existing SQLite file** (e.g. `data.db` from an older install):
  copy it into the `app_var` volume and point `DATABASE_URL` at it.

  ```dotenv
  DATABASE_URL="sqlite:////app/var/data.db"
  COMPOSE_PROFILES=
  ```

  ```
  docker compose cp ./your-old-data.db app:/app/var/data.db
  docker compose restart app
  ```

- **An existing MySQL/MariaDB or Postgres server**: set `DATABASE_URL`
  to its DSN and disable the bundled Postgres (`COMPOSE_PROFILES=`):

  ```dotenv
  DATABASE_URL="mysql://user:pass@192.168.1.10:3306/strichliste?serverVersion=10.11.2-MariaDB&charset=utf8mb4"
  COMPOSE_PROFILES=
  ```

- **An old dump, loaded into the bundled Postgres**: keep
  `COMPOSE_PROFILES=database`, start the stack, then restore into it —
  `docker compose exec -T database psql -U strichliste strichliste < dump.sql`.

**Back up first** — and on MySQL/MariaDB this is not optional: DDL there
is not transactional, so a forward migration that fails halfway cannot
roll itself back. The pre-migration backup is your only safety net (see
the backup one-liners below).

Coming from the much older **strichliste 1** (different schema)? That one
*does* need a conversion: `php bin/console app:import old.sqlite`. It
**replaces** all current data and refuses to run against a non-empty
database without `--force`.

### Backup, upgrades, rollback

The state lives in named volumes: `database_data` (Postgres),
`app_var` (the SQLite database, if you use one) and `caddy_data` (the
TLS certificates / local CA). **`docker compose down -v` deletes all of
them — and with them every balance.** Plain `down` / `up` is always
safe.

Back up before every upgrade (cron-able one-liners):

```
# bundled Postgres
docker compose exec database pg_dump -U strichliste strichliste > strichliste-$(date +%F).sql

# SQLite (consistent snapshot even while running)
docker compose exec app php bin/console dbal:run-sql "VACUUM INTO '/app/var/backup.db'"
docker compose cp app:/app/var/backup.db strichliste-$(date +%F).db
```

Restore Postgres with `docker compose exec -T database psql -U
strichliste strichliste < dump.sql` (into an empty database).

Upgrading is `git pull && make prod` — migrations apply automatically
on boot, and `make prod` re-pulls the base images so FrankenPHP/PHP and
Postgres security patches arrive too (a plain `up --build` reuses the
cached base layers forever). To roll back to an older build afterwards, first revert the
schema **while the new code still runs**:

```
docker compose exec app php bin/console doctrine:migrations:migrate prev --no-interaction
```

then start the older build (or simply restore the backup). Note for
MariaDB/MySQL users: DDL is not transactional there, so a migration
that fails halfway cannot roll itself back — on those databases the
pre-upgrade backup is your only safety net.

## Run locally (without Docker)

Point `.env.local` at a database first — SQLite needs nothing else:

```
echo 'DATABASE_URL="sqlite:///%kernel.project_dir%/var/dev.db"' > .env.local
```

```
composer install
php bin/console doctrine:migrations:migrate
php bin/console importmap:install
APP_ENV=dev APP_DEBUG=1 symfony serve
```

Open `http://127.0.0.1:8000`. To use the compose Postgres instead, run
`docker compose up -d database` and set
`DATABASE_URL="postgresql://strichliste:strichliste@127.0.0.1:5433/strichliste?serverVersion=16&charset=utf8"`
in `.env.local` (the dev override publishes it loopback-only on 5433).

For a bare-metal production build:

```
composer install --no-dev --optimize-autoloader
php bin/console importmap:install
php bin/console asset-map:compile
```

## Configuration

All app-level settings live in `config/strichliste.yaml` (currency, idle
timeout, deposit/dispense step buttons, account boundaries, PayPal,
etc.). The same settings power both the Twig UI and the `/api/settings`
endpoint.

## Tests

- `php bin/phpunit` runs the existing API tests; the API contract must
  stay byte-identical after any UI change.

## Demo

[demo.strichliste.org](https://demo.strichliste.org)
