# Strichliste Backend — Go Rewrite Roadmap

## Goal

Rewrite the existing Symfony/PHP strichliste backend as a **single Go binary**,
serving a JSON API that is **100% compatible** with the current PHP API.

This document is the big-picture roadmap only. Each epic gets its own refinement
afterwards. **No coding happens from this document.**

## Decisions (locked in)

| Topic | Decision |
|-------|----------|
| Language / artifact | Single self-contained Go binary |
| HTTP framework | **Gin** |
| ORM / DB access | **GORM** |
| Database | **PostgreSQL only** (latest version; no legacy support) |
| Schema management | **GORM auto-migrate** — no migration files, fresh schema |
| Config | DB + runtime from **env vars**; settings exposed at `/api/settings` keep the existing **`strichliste.yaml`** format for drop-in compat |
| Frontend | **API only** — no static frontend serving; CORS like the current nelmio setup |
| Compatibility verification | **Derived from the PHP source** (controllers/serializers/services) as the documented contract |
| Reference API for cross-checks | `https://demo.strichliste.org/api/` (when reachable) |
| Tests | Unit tests for functions (run them); integration tests for the API |
| PHP removal | **End of Epic 5**, once the Go port is fully verified |
| Commit cadence | Commit often throughout |

## API surface to reproduce (from PHP source)

All amounts are in **cents**. Base path `/api`.

**Settings**
- `GET /api/settings`

**Users**
- `GET /api/user`
- `POST /api/user`
- `GET /api/user/search`
- `GET /api/user/{userId}`
- `POST /api/user/{userId}`

**Transactions**
- `GET /api/transaction`
- `GET /api/user/{userId}/transaction`
- `POST /api/user/{userId}/transaction`
- `GET /api/user/{userId}/transaction/{transactionId}`
- `DELETE /api/user/{userId}/transaction/{transactionId}`

**Articles**
- `GET /api/article`
- `POST /api/article`
- `GET /api/article/search`
- `GET /api/article/{articleId}`
- `POST /api/article/{articleId}`
- `DELETE /api/article/{articleId}`

**Barcodes**
- `GET /api/barcode`
- `GET /api/article/{articleId}/barcode`
- `GET /api/article/{articleId}/barcode/{barcodeId}`
- `POST /api/article/{articleId}/barcode`
- `DELETE /api/article/{articleId}/barcode/{barcodeId}`

**Tags**
- `GET /api/tag`
- `GET /api/article/{articleId}/tag`
- `GET /api/article/{articleId}/tag/{tagId}`
- `POST /api/article/{articleId}/tag`
- `DELETE /api/article/{articleId}/tag/{tagId}`

**Metrics**
- `GET /api/metrics`
- `GET /api/user/{userId}/metrics`

**Index**
- `GET /` (index)

Domain entities: `User`, `Article`, `ArticleTag`, `Barcode`, `Tag`, `Transaction`.

## Epic structure

Sequencing is **hybrid**: a layered foundation in Epic 1 (scaffold + all DB
models + a first working endpoint), then **vertical domain slices** for Epics
2–5. Every epic ends with something a human can verify in a browser / via HTTP.

Cross-cutting rules applied to **every** epic:
- Unit tests for new functions, executed and passing.
- Integration tests for every new endpoint, asserting the PHP-derived contract.
- Frequent commits.
- Endpoints behave identically to PHP: same status codes, JSON keys, error
  envelope, pagination, and amount/cents semantics.

---

### Epic 1 — Foundation & Settings

Stand up the Go skeleton and prove the full stack works end-to-end.

- Go module + project layout; Gin server; graceful startup/shutdown.
- Config loader: env vars for Postgres connection + server, `strichliste.yaml`
  loader for the settings tree.
- GORM connection to Postgres + **auto-migrate of all entities** (User, Article,
  ArticleTag, Barcode, Tag, Transaction) so the schema exists from the start.
- Cross-cutting middleware: CORS (matching nelmio config), consistent JSON error
  envelope matching PHP, request logging.
- First working endpoints: `GET /` (index) and `GET /api/settings` returning the
  settings tree in the exact PHP shape.

**Browser-verifiable:** open `/api/settings` and `/` and see correct responses;
DB tables created on boot.

---

### Epic 2 — Users & Transactions

The core domain: accounts and money movement.

- User model + repository/service; user list, create, search, get, update.
- Account balance handling and boundary enforcement (`account.boundary`).
- Transaction creation (deposit/dispense/transfer/purchase), undo/delete rules
  (`payment.undo` timeout + delete flag), split-invoice, transaction boundaries.
- Endpoints: all `/api/user...` and `/api/...transaction...` routes above.

**Browser-verifiable:** create a user, deposit/spend, list transactions, undo a
transaction — all via the API.

---

### Epic 3 — Articles & Barcodes

Purchasable items and their barcode lookups.

- Article model + service; create/list/search/get/update/soft-delete with
  precursor/active-version semantics matching PHP.
- Barcode model + service tied to articles; all barcode routes.
- Article purchase integration with the transaction flow from Epic 2.

**Browser-verifiable:** create an article, add a barcode, look it up, purchase it
as a transaction.

---

### Epic 4 — Tags

Article categorization.

- Tag and ArticleTag models + service (multiple tags per article, per recent
  schema change).
- All `/api/tag` and `/api/article/{id}/tag...` routes.

**Browser-verifiable:** tag/untag articles and list tags via the API.

---

### Epic 5 — Metrics, Hardening & PHP Removal

Finish the contract and decommission the old stack.

- `GET /api/metrics` and `GET /api/user/{userId}/metrics` (aggregations).
- Full compatibility hardening pass: edge cases, error formats, pagination,
  date/timezone/currency formatting, cents handling — cross-checked against the
  PHP source and, where reachable, `demo.strichliste.org`.
- Complete the API integration test suite covering all endpoints.
- **Remove all PHP/Symfony/Composer artifacts** (`src/`, `config/`, `bin/`,
  `public/`, `vendor/`, `migrations/`, `composer.*`, `symfony.lock`,
  `phpunit.*`, etc.) once Go parity is verified. Update README/docs for the Go
  binary.

**Browser-verifiable:** metrics endpoints return correct data; the whole API runs
from the single Go binary with no PHP present.

## Definition of done (overall)

- Single Go binary serves every endpoint listed above, byte/shape-compatible with
  the PHP API.
- PostgreSQL schema created via GORM auto-migrate; no migration files.
- Unit + integration tests green.
- No PHP/Symfony code remains in the repository.
