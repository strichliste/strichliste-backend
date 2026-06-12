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
  (Swagger UI; raw spec at `/api/doc.json`), maintained in
  `config/packages/nelmio_api_doc.yaml`.

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

### Development

```
docker compose up -d --build --wait
```

Open **`https://localhost`**. Caddy serves TLS out of the box using its
local CA, so browsers (which try HTTPS first these days) connect
directly; plain `http://localhost` is redirected. On the first visit
your browser shows a certificate warning — accept it once, or trust the
CA root permanently (works on Linux, macOS and Windows):

```
make tls
```

The CA lives in the `caddy_data` volume, so the trust survives
container rebuilds. The entrypoint waits for the database, installs
`vendor/` if missing and applies migrations before serving traffic;
code changes restart the worker automatically (`watch` mode). To
step-debug, set `XDEBUG_MODE=debug` in `.env`. `make help` lists the
shortcuts (`make up`, `make logs`, `make test`, ...).

### Production

Set a real `APP_SECRET` in `.env` (the committed value is publicly
known; the container warns loudly if you keep it), then:

```
docker compose -f compose.yaml -f compose.prod.yaml up -d --build --wait
```

or `make prod`. The prod image bakes the code, compiled assets and a
warmed cache; nothing is bind-mounted. Migrations still run on boot.

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
  on boot so it is picked up:

  ```yaml
  volumes:
    - ./config/strichliste.yaml:/app/config/strichliste.yaml:ro
  ```

- **Health checks and restart policies** are preconfigured; `--wait`
  blocks until the app actually serves traffic.

Single container without compose:

```
docker build --target frankenphp_prod -t strichliste .
docker run -d -p 80:80 -p 443:443 -p 443:443/udp \
  -e SERVER_NAME=localhost \
  -e DATABASE_URL="sqlite:////app/var/data.db" \
  -e APP_SECRET="$(openssl rand -hex 32)" \
  -v strichliste-data:/app/var \
  strichliste
```

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
`DATABASE_URL="postgresql://strichliste:!ChangeMe!@127.0.0.1:5433/strichliste?serverVersion=16&charset=utf8"`
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
