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

The previous standalone React SPA (`strichliste-web-frontend/`) has been
removed; its README and roadmap are preserved under `../specs/_archive/`
for historical reference.

## Run with Docker (recommended)

The repository ships a production-ready container setup:
[FrankenPHP](https://frankenphp.dev/) (PHP 8.5, worker mode — Symfony
boots once and stays resident) plus Postgres 16.

```
docker compose up -d --build
```

Open `http://localhost:8080`. That's all — the entrypoint waits for the
database and applies migrations automatically before serving traffic.

What the image does for you:

- **Migrations on boot** — disable with `AUTO_MIGRATE=0` (e.g. when
  running several replicas).
- **Settings without rebuilding** — bind-mount your own settings file;
  the entrypoint recompiles the container cache on boot so it is picked
  up (see the commented `volumes:` block in `compose.yaml`):

  ```yaml
  volumes:
    - ./config/strichliste.yaml:/app/config/strichliste.yaml:ro
  ```

- **Health checks and restart policies** are preconfigured for both
  containers.

Environment knobs (set under `environment:` in `compose.yaml` or via
`docker run -e`):

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_SECRET` | dev value (publicly known!) | Set a unique secret for any real deployment — the entrypoint warns loudly if you don't. |
| `SERVER_NAME` | `:80` (plain HTTP) | Set a hostname (e.g. `strichliste.example.com`) to let Caddy obtain TLS certificates automatically; also publish port 443. |
| `DATABASE_URL` | Postgres from compose | Any Doctrine DSN. SQLite works for small setups (see below). |
| `AUTO_MIGRATE` | `1` | Run pending migrations on container start. |
| `FRANKENPHP_CONFIG` | `worker ./public/index.php` | Unset (empty) to fall back to classic one-process-per-request mode. |

Single container with SQLite instead of Postgres:

```
docker build -t strichliste .
docker run -d -p 8080:80 \
  -e DATABASE_URL="sqlite:////app/var/data.db" \
  -e APP_SECRET="$(openssl rand -hex 16)" \
  -v strichliste-data:/app/var \
  strichliste
```

Note: the compose file publishes Postgres on `127.0.0.1:5433` (loopback
only — it uses default credentials) so a non-Docker dev setup can share
the same database.

## Run locally (without Docker)

```
composer install
php bin/console doctrine:migrations:migrate
php bin/console importmap:install
APP_ENV=dev APP_DEBUG=1 symfony serve
```

Open `http://127.0.0.1:8000`. The default `DATABASE_URL` in `.env`
expects the compose Postgres on port 5433 (`docker compose up -d
database`); for a dependency-free setup, point `.env.local` at SQLite:

```
DATABASE_URL="sqlite:///%kernel.project_dir%/var/dev.db"
```

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
