# Epic 1 — Foundation & Settings (Refinement)

Stand up the Go skeleton (Gin + GORM + PostgreSQL) and prove the full stack
end-to-end with two real, browser-verifiable endpoints: `GET /` and
`GET /api/settings`. All cross-cutting behavior (CORS, error envelope, response
headers, JSON body parsing) is established here so later epics just add routes.

PHP stays in the repo as the reference until Epic 5.

## Locked decisions for this epic

| Topic | Decision |
|-------|----------|
| Module path | `github.com/strichliste/strichliste-backend` |
| Error `class` field | **Go-equivalent identifiers** (e.g. `UserNotFoundException`), not the literal PHP FQCN. Same JSON shape, `code`, and `message`. **Accepted divergence from byte-for-byte compat** — documented below. |
| DB config | **Reuse `DATABASE_URL`** as a single `postgres://` URL |
| Settings source | **Keep `config/strichliste.yaml` as-is** (the `parameters.strichliste` tree), path configurable via env |
| Framework / ORM / DB | Gin / GORM / PostgreSQL-only, GORM auto-migrate |

## Cross-cutting contract (extracted from PHP source)

These must match the PHP behavior and are implemented once in Epic 1.

### Error envelope
PHP `ApiExceptionSubscriber` emits, for any `ApiException`:
```json
{ "error": { "class": "...", "code": <int>, "message": "..." } }
```
Go port: same structure; HTTP status == `code`; `class` carries the
Go-equivalent identifier (e.g. `UserNotFoundException`). A typed `APIError`
(code, class, message) plus a Gin error-handling middleware reproduces this.
Non-API errors (panics) → generic `500`.

### Response headers
PHP `ApiResponseSubscriber` sets on **every** response:
```
Cache-Control: no-cache, max-age=0, must-revalidate, no-store
```
Go port: middleware adding the identical `Cache-Control` directives globally.

### JSON request-body handling
PHP `BeforeActionSubscriber`: when content-type is JSON and body is non-empty,
decode it; on invalid JSON throw `BadRequestHttpException` →
`invalid json body: <reason>` with status `400`.
Go port: body-decode helper / middleware reproducing the same 400 + message
prefix. (Mostly exercised in Epic 2+, but the helper lands here.)

### CORS (from `nelmio_cors.yaml`, path `^/api`)
- `Access-Control-Allow-Origin: *`
- `Access-Control-Allow-Headers: *`
- `Access-Control-Allow-Methods: POST, PUT, GET, DELETE`
- `Access-Control-Max-Age: 3600`
Go port: CORS middleware scoped to `/api`, plus preflight `OPTIONS` handling.

## Endpoints delivered in Epic 1

### `GET /` (index)
PHP serves `webroot/index.html`, or the literal string `Front-End is missing!`
when absent. Since this is **API-only**, the Go port returns
`Front-End is missing!` (HTTP 200, text) unless a configured webroot file
exists. Behavior-compatible with a backend that has no bundled frontend.

### `GET /api/settings`
Returns the whole settings tree wrapped as:
```json
{ "settings": { ...parameters.strichliste tree... } }
```
Implementation: load `strichliste.yaml`, navigate to `parameters.strichliste`,
serialize that subtree verbatim. Value **types must be preserved** (ints for
deposit/dispense `steps` and boundaries, bools for `enabled`/`autoOpen`, strings
for `stalePeriod`/formats/currency). JSON object **key ordering is treated as
insignificant** (clients parse objects, not byte streams) — accepted.

## Data model & schema (GORM auto-migrate)

All six entities are defined and auto-migrated in Epic 1 so the schema exists
from first boot, even though their endpoints arrive in later epics. Fields and
types mirror the Doctrine entities.

**Critical compat detail — table names.** GORM's default pluralization is wrong
here; each model needs an explicit `TableName()`:

| Model | Table |
|-------|-------|
| User | `user` (reserved word — must be quoted) |
| Article | `article` |
| ArticleTag | `article_tag` |
| Barcode | `barcode` |
| Tag | `tag` |
| Transaction | `transactions` |

Field summary (types per Doctrine):
- **User**: id (PK int), name (varchar 64, unique), email (varchar 255, null),
  balance (int, default 0), disabled (bool, default false), created (datetime),
  updated (datetime, null).
- **Article**: id, name (varchar 255), amount (int), precursor_id (self FK,
  one-to-one, null), active (bool, default true), usage_count (int, default 0),
  created (datetime). Relations: barcodes, articleTags.
- **ArticleTag**: id, article_id (FK), tag_id (FK), created (datetime).
- **Barcode**: id, barcode (varchar 32, not null), article_id (FK),
  created (datetime).
- **Tag**: id, tag (varchar, not null, default ''), created (datetime).
- **Transaction**: id, user_id (FK, null), quantity (int, null),
  article_id (FK, null), recipient_transaction_id (self FK one-to-one, null),
  sender_transaction_id (self FK one-to-one, null), comment (varchar 255, null),
  amount (int), deleted (bool, default false), created (datetime).

Auto-migrate runs on startup and must be idempotent across reboots.

## Configuration

Loaded at startup, env-driven:
- `DATABASE_URL` — `postgres://user:pass@host:port/dbname?sslmode=...` (required).
- `STRICHLISTE_SETTINGS_FILE` — path to `strichliste.yaml`
  (default `config/strichliste.yaml`).
- Server bind address / port — env (e.g. `LISTEN_ADDR`, default `:8080`).
- Optional `WEBROOT` for the index file.

Fail fast with a clear message if `DATABASE_URL` is missing or the DB/settings
file is unreachable.

## Proposed project layout

```
cmd/strichliste/main.go      // entrypoint: load config, connect+migrate, run server
internal/config/             // env + strichliste.yaml loading
internal/server/             // Gin engine, middleware (CORS, cache headers, errors), router wiring
internal/model/              // GORM models + TableName() overrides
internal/apierror/           // APIError type + error-envelope helpers
internal/settings/           // settings tree loader + /api/settings handler
internal/index/              // GET / handler
go.mod  // module github.com/strichliste/strichliste-backend
```
(Final names refined during implementation; this is the intended shape.)

## Testing

**Unit tests (written and executed):**
- Config loader: valid/invalid `DATABASE_URL`, default fallbacks.
- Settings loader: parses `strichliste.yaml`, extracts `parameters.strichliste`,
  preserves value types.
- `APIError` → error-envelope JSON shape and status mapping.
- Model `TableName()` returns the exact table names above.

**Integration tests (HTTP, against a real Postgres):**
- Boot the server against a test Postgres (Dockerized / testcontainers or a
  CI service), confirm auto-migrate creates all six tables with correct names.
- `GET /api/settings` → 200, body `{"settings": {...}}` matching the YAML,
  with correct value types and the `Cache-Control` header present.
- `GET /` → 200 `Front-End is missing!` when no webroot.
- CORS: `OPTIONS /api/settings` and a `GET` return the expected
  `Access-Control-*` headers.
- Error envelope: a deliberately triggered API error returns the
  `{"error":{class,code,message}}` shape with matching status.

Where reachable, cross-check `GET /api/settings` shape against
`https://demo.strichliste.org/api/settings`.

## Definition of done (Epic 1)

- `go build` produces a single binary; `go test ./...` passes.
- Binary boots, connects to Postgres, auto-migrates all six tables (correct
  names), and serves `GET /` and `GET /api/settings`.
- CORS, `Cache-Control` headers, and the JSON error envelope are in place and
  covered by tests.
- `GET /api/settings` is byte/shape-compatible with the PHP response (modulo
  JSON key ordering).
- Browser check: hitting `/api/settings` and `/` in a browser returns the
  correct responses.
- PHP code untouched (removal deferred to Epic 5).
- Work committed in small, frequent commits.

## Known / accepted divergences

- Error `class` uses Go-equivalent identifiers, not PHP FQCNs (per decision).
- JSON object key ordering is not guaranteed identical to PHP.
- `GET /` always reports "Front-End is missing!" unless a webroot is configured
  (API-only scope).
