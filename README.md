# strichliste-backend

strichliste ([ʃtʀɪçˈlɪstə], German word for tally sheet) is a tool to replace a
tally sheet inside a hackerspace. It provides a no-frills, easy-to-setup
solution for managing your organization's snack bar.

This is a **single-binary Go implementation** of the strichliste backend. It is
API-compatible with the original PHP/Symfony backend, talks to **PostgreSQL**,
and creates its schema automatically on first start (no migration step).

## How it works

Each user has an account. Buying an item deducts its value from the account;
charging the account adds to it. Administrators can configure upper/lower bounds
for balances and transactions. See the live demo at https://demo.strichliste.org.

## Requirements

- Go 1.24+ (to build)
- PostgreSQL (latest stable recommended)

## Configuration

The server is configured through environment variables:

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `DATABASE_URL` | yes | – | PostgreSQL URL, e.g. `postgres://user:pass@localhost:5432/strichliste?sslmode=disable` |
| `LISTEN_ADDR` | no | `:8080` | Address the HTTP server binds to |
| `STRICHLISTE_SETTINGS_FILE` | no | `config/strichliste.yaml` | Path to the settings file served at `/api/settings` |
| `WEBROOT` | no | – | Optional directory containing `index.html` to serve at `/` |

Application settings (boundaries, undo behaviour, currency, deposit steps, …)
live in `config/strichliste.yaml` and are exposed verbatim at `GET /api/settings`.

See `.env.example` for a starting point.

## Run with Docker (recommended)

The repository ships a multi-stage `Dockerfile` and a `docker-compose.yml` that
starts the app together with PostgreSQL:

```sh
docker compose up --build
```

The API is then available on http://localhost:8080 (e.g. `GET /api/settings`).
Postgres data persists in the `pgdata` volume; stop and wipe it with
`docker compose down -v`.

To build just the image:

```sh
docker build -t strichliste-backend .
docker run --rm -p 8080:8080 \
  -e DATABASE_URL="postgres://user:pass@host:5432/strichliste?sslmode=disable" \
  strichliste-backend
```

## Build & run locally (without Docker)

```sh
go build -o strichliste ./cmd/strichliste

export DATABASE_URL="postgres://strichliste:strichliste@localhost:5432/strichliste?sslmode=disable"
./strichliste
```

The schema is created/updated automatically on startup.

## Tests

```sh
# Unit tests run without a database.
go test ./...

# Integration tests require a PostgreSQL instance; point TEST_DATABASE_URL at it.
# (Tests that need a DB are skipped when it is unset.)
export TEST_DATABASE_URL="postgres://strichliste:strichliste@localhost:5432/strichliste?sslmode=disable"
go test ./...
```

A throwaway PostgreSQL for testing:

```sh
docker run -d --name strichliste-pg \
  -e POSTGRES_USER=strichliste -e POSTGRES_PASSWORD=strichliste -e POSTGRES_DB=strichliste \
  -p 5432:5432 postgres:17-alpine
```

## API documentation

The HTTP API is documented in [`docs/API.md`](docs/API.md). The implementation
plan and per-epic specs live in [`specs/`](specs/).
