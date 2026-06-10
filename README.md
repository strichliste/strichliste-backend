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

Open **`https://localhost`**. Caddy serves TLS out of the box using its
local CA, so browsers (which try HTTPS first these days) connect
directly; plain `http://localhost` is redirected. On the first visit
your browser shows a certificate warning — accept it once, or trust the
CA root permanently (works on Linux, macOS and Windows):

```
make tls
```

The CA lives in the `caddy_data` volume, so the trust survives
container rebuilds. The entrypoint waits for the database and applies
migrations automatically before serving traffic. `make help` lists the
other shortcuts (`make up`, `make logs`, `make test`, ...).

If host ports 80/443 are already taken, remap them:

```
HTTP_PORT=8080 HTTPS_PORT=8443 HTTP3_PORT=8443 docker compose up -d
```

and open `https://localhost:8443` directly (the HTTP→HTTPS redirect
targets the standard port, so skip the HTTP URL when remapping).

What the image does for you:

- **Migrations on boot** — the entrypoint waits for the database and
  applies pending migrations before serving traffic.
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
| `SERVER_NAME` | `localhost` (self-signed TLS) | Set a real hostname (e.g. `strichliste.example.com`) for automatic Let's Encrypt certificates, or `":80"` for plain HTTP only (e.g. LAN-IP kiosks). |
| `HTTP_PORT` / `HTTPS_PORT` / `HTTP3_PORT` | `80` / `443` / `443` | Published host ports. |
| `POSTGRES_USER` / `POSTGRES_PASSWORD` / `POSTGRES_DB` / `POSTGRES_VERSION` | `strichliste` / `strichliste` / `strichliste` / `16` | Database credentials shared by both services. |
| `DATABASE_URL` | Postgres from compose | Any Doctrine DSN. SQLite works for small setups (see below). |
| `FRANKENPHP_CONFIG` | `worker ./public/index.php` | Unset (empty) to fall back to classic one-process-per-request mode. |

Single container with SQLite instead of Postgres:

```
docker build -t strichliste .
docker run -d -p 80:80 -p 443:443 -p 443:443/udp \
  -e SERVER_NAME=localhost \
  -e DATABASE_URL="sqlite:////app/var/data.db" \
  -e APP_SECRET="$(openssl rand -hex 16)" \
  -v strichliste-data:/app/var \
  strichliste
```

(The bare image defaults to plain HTTP on `:80`; `SERVER_NAME=localhost`
enables the same self-signed TLS the compose setup uses.)

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
