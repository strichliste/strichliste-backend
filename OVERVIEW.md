# strichliste — Project Overview

strichliste ([ʃtʀɪçˈlɪstə], the German word for *tally sheet*) replaces
the paper tally sheet next to the fridge in a hackerspace, club room or
small office. Members pick their name on a shared screen, tap what they
took (a drink, a snack) or how much money they put into the cash box,
and strichliste keeps everyone's balance. It is built around the same
trust a paper list has — **there are no logins, passwords or user
roles**. Anyone standing at the kiosk can book on any account, just
like anyone could make a pencil mark on paper. What you gain over paper
is arithmetic that is always correct, an undo button, statistics, and
an API for barcode scanners and phone apps.

A public demo of the (older) version runs at
[demo.strichliste.org](https://demo.strichliste.org); the project
website is [strichliste.org](https://www.strichliste.org/).

**Quick orientation:**

| You want to… | Read |
| --- | --- |
| Try it on your machine in 5 minutes | [Getting started](#getting-started-in-five-minutes) |
| Install it permanently for your space | [Running in production](#running-in-production--installation) |
| Tune currency, limits, buttons, PayPal… | [Configuration reference](#configuration-reference-configstrichlisteyaml) |
| Build a client / script against the API | [The REST API](#the-rest-api) |
| Understand the code | [How the application works](#how-the-application-works) |

---

## What it does (feature tour)

- **User accounts** — anyone can add themselves at the kiosk with just
  a name (optionally an e-mail). Users who haven't booked anything for
  a while (default: 10 days) are tucked away on an "inactive" tab to
  keep the main list short; they reappear on their next booking.
  Accounts can be disabled (e.g. people who left) without deleting
  their history.
- **Deposit / dispense** — put money in or take money out, via
  configurable one-tap amount buttons (50 ct, 1 €, 2 €, …) or a free
  amount field. Balances may go negative down to a configurable limit,
  so "I'll pay next week" works — but only up to the boundary you set.
- **Articles with barcodes** — maintain a product list ("Club-Mate,
  1.50 €") and buy with one tap. Articles can carry **barcodes**: on a
  user's page, a USB barcode scanner works out of the box (scans are
  recognized by their fast keystroke burst — no driver, no
  configuration) and books the matching article instantly.
- **Undo** — every transaction shows an undo button for a configurable
  grace period (default: 5 minutes), so a slipped finger is not a
  treasury incident.
- **Transfers** — send money from one account to another, with an
  optional comment ("thanks for the pizza").
- **Split invoice** — one person paid for the group order; this page
  splits the total across any set of members in one step.
- **Statistics** — a metrics page for the whole system (transaction
  volume, top articles, activity charts) and a personal metrics page
  per user.
- **PayPal top-up** *(optional)* — users can settle their balance via
  paypal.me-style payment links, with a configurable percentage fee
  passed on to the payer.
- **Search** — find users and articles from the header on every page.
- **Localization** — the interface ships in **English and German** and
  follows the configured currency (name, symbol, ISO code) everywhere.
- **REST API + OpenAPI docs** — everything above is also scriptable;
  see [The REST API](#the-rest-api).

Everything monetary in strichliste — config values, API payloads,
database rows — is an **integer number of cents**. `1.50 €` is `150`.
There is no floating point money anywhere.

---

## How the application works

strichliste is a **single Symfony 7.4 application** (PHP 8.4+). One
process serves two faces:

1. **The web UI** — server-rendered Twig pages at the user-facing
   routes (`/`, `/user/active`, `/user/{id}`, `/articles`,
   `/split-invoice`, `/metrics`, `/search-results`). This is the
   primary interface, designed for a wall-mounted kiosk:
   - It is **fully operable without JavaScript** — every action is a
     real HTML form. JavaScript (Stimulus controllers + Turbo) only
     layers comfort on top: snappier navigation, the barcode listener,
     an idle timer that returns the kiosk to the user list.
   - The look and feel matches the classic strichliste interface that
     long-time users know from the React version.
2. **The legacy REST API** at `/api/*`, kept for existing third-party
   clients (Android apps, kiosk hardware, space-automation scripts).
   The API contract is **frozen**: JSON shapes are byte-compatible with
   strichliste v1.8 and pinned by an extensive test suite
   (`tests/Controller/Api/`).

### OpenAPI / Swagger

The API is documented as an OpenAPI 3 specification, served by the
application itself:

- **`/api/doc`** — interactive Swagger UI (browse endpoints, try
  requests against your own instance).
- **`/api/doc.json`** — the raw OpenAPI document, ready for code
  generators or Postman/Insomnia import.

The spec is hand-maintained in `config/packages/nelmio_api_doc.yaml`
(deliberately not generated from code, so the frozen contract can't
drift by accident). A prose version lives in `docs/API.md`.

### Code map

| Path | What lives there |
| --- | --- |
| `src/Controller/Ui/` | The Twig UI controllers (users, articles, transactions, split invoice, metrics, search, PayPal). |
| `src/Controller/Api/` | The frozen JSON API controllers, one per resource. |
| `src/Service/` | Business logic shared by both: `TransactionService` (balance math, boundaries, undo), `UserService`, `ArticleService`, `MetricsService`, `SettingsService`, `MoneyParser`. |
| `src/Entity/` + `src/Repository/` | Doctrine entities (User, Article, Transaction, Barcode, Tag) and queries. |
| `src/Serializer/` | Produces the exact legacy JSON shapes for `/api/*`. |
| `src/Command/` | CLI tools — see [Console commands](#console-commands). |
| `config/strichliste.yaml` | All application settings — see the [reference](#configuration-reference-configstrichlisteyaml). |
| `templates/`, `assets/` | Twig templates, Stimulus controllers, CSS (no build step — AssetMapper + importmap). |
| `migrations/` | Database schema migrations (applied automatically in Docker). |
| `tests/` | PHPUnit suite pinning the API contract + Playwright end-to-end suite for the UI. |

### Database

Storage goes through Doctrine ORM/DBAL, so the database is a
connection-string choice, not a code choice. **SQLite**, **MySQL /
MariaDB** and **PostgreSQL** are all supported and exercised in CI.
Rules of thumb:

- **SQLite** — perfect for a single kiosk in a small space; zero
  administration, one file to back up.
- **PostgreSQL / MariaDB** — pick one of these when several devices
  write at once or the instance is long-lived and busy. (The bundled
  Docker setup ships Postgres by default.)

---

## Getting started in five minutes

With a reasonably current Docker (Engine 25+, Compose v2.30+):

```bash
git clone https://github.com/strichliste/strichliste-backend.git
cd strichliste-backend
docker compose up -d --build --wait
```

Open **https://localhost** (accept the one-time certificate warning, or
run `make tls` to trust the local CA). You get a dev environment with
Postgres, live code reload and the database schema already migrated.
Add a user, add an article under *Articles*, buy it — that's the whole
loop.

No Docker? With PHP ≥ 8.4 and Composer installed:

```bash
echo 'DATABASE_URL="sqlite:///%kernel.project_dir%/var/dev.db"' > .env.local
composer install
php bin/console doctrine:migrations:migrate
php bin/console importmap:install
php -S 127.0.0.1:8000 -t public   # or: symfony serve
```

---

## Running in production / installation

> The short version: **use Docker**, set two values in `.env`, run one
> command. The [README](README.md#run-with-docker-recommended) is the
> full operations manual (TLS options, every knob, backup & restore,
> upgrades, rollback); this section is the orientation.

### Docker (recommended)

The repository ships a production-grade container setup modeled on
[dunglas/symfony-docker](https://github.com/dunglas/symfony-docker):
FrankenPHP (Caddy + PHP 8.5) running the app in **worker mode** —
Symfony boots once and stays resident, which a Raspberry-Pi-class kiosk
box appreciates.

1. Edit `.env`:
   - set a unique `APP_SECRET` (`openssl rand -hex 32`),
   - optionally set `SERVER_NAME`:
     - a real hostname → automatic Let's Encrypt certificates,
     - `localhost` (default) → self-signed local CA,
     - `":80"` → plain HTTP, for LAN-IP kiosks without a domain.
2. Start it:

   ```bash
   docker compose -f compose.yaml -f compose.prod.yaml up -d --build --wait
   # or: make prod
   ```

The container **waits for the database and applies migrations on every
boot** — first install and upgrades are the same command. Health
checks, restart policy and log rotation are preconfigured.

**Choosing the database is a pure `.env` decision** — bundled Postgres
by default; or set `DATABASE_URL` to any Doctrine DSN (SQLite file,
external MariaDB/MySQL or Postgres) and switch the bundled Postgres off
with `COMPOSE_PROFILES=` (empty). The image contains all three drivers.

**Before you rely on it for real money, read the
[Backup, upgrades, rollback](README.md#backup-upgrades-rollback)
section of the README.** It is short, tested, and the difference
between "restore from last night" and a shoebox full of receipts.

### Bare metal (without Docker)

The classic setup the old website describes still works, modernized:

1. **Requirements**: PHP ≥ 8.4 with `intl`, `ctype`, `iconv`, `json`
   and the PDO driver for your database (`pdo_sqlite`, `pdo_mysql` or
   `pdo_pgsql`); a web server with PHP-FPM.
2. **Get the code**: download a release tarball (ships with `vendor/`
   and pre-compiled assets) and extract it to e.g.
   `/var/www/strichliste`, or build from a git checkout:

   ```bash
   composer install --no-dev --optimize-autoloader
   php bin/console importmap:install
   php bin/console asset-map:compile
   ```

3. **Configure the database** via environment (or `.env.local`):

   ```bash
   DATABASE_URL="mysql://strichliste:PASSWORD@localhost/strichliste?serverVersion=10.11.2-MariaDB"
   php bin/console doctrine:migrations:migrate
   ```

4. **Configure the web server**: point the document root at `public/`
   and route everything through `public/index.php`. Working nginx
   (plain + SSL) and Apache examples live in [`examples/`](examples/).
5. Set `APP_ENV=prod`, `APP_DEBUG=0` and a unique `APP_SECRET` in the
   environment, then warm the cache: `php bin/console cache:clear`.

---

## Configuration reference (`config/strichliste.yaml`)

All application-level behavior is configured in **one file**,
`config/strichliste.yaml`, under the `parameters.strichliste` key. The
same values drive the web UI **and** are exposed verbatim to API
clients via `GET /api/settings`.

Applying changes:

- **Bare metal**: edit the file, then `php bin/console cache:clear`.
- **Docker**: bind-mount your copy into the container —
  the entrypoint recompiles the cache on every boot, so a restart
  applies it:

  ```yaml
  # compose override for the app service
  volumes:
    - ./config/strichliste.yaml:/app/config/strichliste.yaml:ro
  ```

Two recurring datatypes:

- **money** — always integer **cents**: `1000` = 10.00 €.
- **timeperiod** — a PHP relative date string like `'5 minute'`,
  `'10 day'`, `'2 week'` ([format reference](https://www.php.net/manual/en/datetime.formats.relative.php)).

### `article`

| key | type | default | what it does |
| --- | --- | --- | --- |
| `enabled` | bool | `true` | Master switch for the article system. Off: no *Buy* tab on user pages, no article routes in the UI. |
| `autoOpen` | bool | `false` | When on, a user's page opens directly on the *Buy* tab (instead of the transaction history) — handy for buy-mostly kiosks with scanners. |

### `common`

| key | type | default | what it does |
| --- | --- | --- | --- |
| `idleTimeout` | int (ms) | `30000` | After this many milliseconds without input, the kiosk returns to the user list — so the screen is never left on someone's account page. `0` disables. (Needs JavaScript; without JS there is simply no auto-return.) |

### `paypal`

| key | type | default | what it does |
| --- | --- | --- | --- |
| `enabled` | bool | `false` | Show a PayPal top-up option on user pages. |
| `recipient` | string | – | The receiving PayPal account (e-mail address). |
| `fee` | int (percent) | `0` | Percentage added **on top** of the chosen amount and paid by the user, so PayPal's cut doesn't drain the cash box. Example: top-up 10 €, `fee: 3` → user pays 10.30 €, account is credited 10 €. |

### `user`

| key | type | default | what it does |
| --- | --- | --- | --- |
| `stalePeriod` | timeperiod | `'10 day'` | Users with no transaction within this period are moved to the *inactive* tab (they are hidden, not deleted, and return on their next booking). |

### `i18n`

| key | type | default | what it does |
| --- | --- | --- | --- |
| `dateFormat` | string | `'YYYY-MM-DD HH:mm:ss'` | Display format for timestamps (moment.js-style tokens; also served to API clients). |
| `timezone` | string | `'auto'` | Timezone for display; `auto` uses the server/browser default. |
| `language` | string | `'en'` | UI language. Shipped: `en`, `de`. |
| `currency.name` | string | `'Euro'` | Currency name. |
| `currency.symbol` | string | `'€'` | Symbol shown next to every amount. |
| `currency.alpha3` | string | `'EUR'` | ISO 4217 code (used e.g. for PayPal). |

### `account.boundary`

| key | type | default | what it does |
| --- | --- | --- | --- |
| `upper` | money | `20000` | Maximum balance (200 €). Transactions that would exceed it are rejected. |
| `lower` | money | `-20000` | Minimum balance (−200 €) — i.e. the credit line you extend to members. |

### `payment.undo`

| key | type | default | what it does |
| --- | --- | --- | --- |
| `enabled` | bool | `true` | Show an undo button on recent transactions. |
| `delete` | bool | `false` | `false`: the undone transaction stays in the history, marked as reverted. `true`: undo removes it from the database entirely. |
| `timeout` | timeperiod | `'5 minute'` | How long a transaction stays undoable. |

### `payment.boundary`

| key | type | default | what it does |
| --- | --- | --- | --- |
| `upper` | money | `15000` | Largest single transaction (150 €) — guards against an accidental extra zero. |
| `lower` | money | `-2000` | Largest single deduction (−20 €). |

### `payment.transactions`

| key | type | default | what it does |
| --- | --- | --- | --- |
| `enabled` | bool | `true` | Allow user-to-user transfers (with optional comment). |

### `payment.splitInvoice`

| key | type | default | what it does |
| --- | --- | --- | --- |
| `enabled` | bool | `true` | Enable the *Split invoice* page (divide one amount across several users). |

### `payment.deposit` / `payment.dispense`

Deposit = putting money in; dispense = taking money out. Both share the
same shape:

| key | type | default | what it does |
| --- | --- | --- | --- |
| `enabled` | bool | `true` | Show this action on user pages. |
| `custom` | bool | `true` | Allow free-form amounts (off: only the step buttons). |
| `steps` | money[] | `[50, 100, 200, 500, 1000]` | The one-tap amount buttons (in cents: 0.50 € … 10 €). |

---

## Console commands

Run with `php bin/console …` — inside Docker:
`docker compose exec app php bin/console …`.

| Command | Purpose |
| --- | --- |
| `app:import <file>` | Import a **strichliste 1** `database.sqlite` (users + transactions) into this version. |
| `app:user:status <user> <true\|false>` | Enable/disable an account by name or id. |
| `app:user:cleanup --days/--months/--years [--minBalance --maxBalance --confirm]` | Bulk-disable accounts inactive for longer than the interval (optionally only within a balance range). |
| `app:retire-data --days/--months/--years [--confirm]` | **Delete** transactions older than the interval — the data-privacy lever. |
| `app:ldapimport --host … --bindDn … --baseDn …` | Create/update users from an LDAP directory (cron-able). Note: needs the `symfony/ldap` package, which is a dev dependency — for production use run `composer require symfony/ldap` (not available in the stock Docker image). |
| `cache:clear` | Apply `strichliste.yaml` changes (bare metal; the Docker entrypoint does this on boot). |
| `doctrine:migrations:migrate` | Apply schema migrations (automatic in Docker). |

---

## The REST API

The `/api/*` endpoints speak the **strichliste v1.8 contract**,
unchanged, so existing clients keep working. Essentials:

- Amounts are **integer cents**; timestamps are `YYYY-MM-DD HH:MM:SS`.
- Request bodies may be JSON or form-encoded.
- Errors use one envelope:
  `{"error": {"class": "…", "code": 4xx, "message": "…"}}`.
- List endpoints paginate with `?limit=…&offset=…`.
- There is **no authentication** — the API trusts the network like the
  kiosk trusts the room. Do not expose it to the open internet
  unprotected (put it behind your space's VPN/LAN, or add auth at the
  reverse proxy).

Resource overview (full, browsable detail at **`/api/doc`**):

| Resource | Endpoints |
| --- | --- |
| Users | `GET/POST /api/user`, `GET/POST /api/user/{id}`, `GET /api/user/search` |
| Transactions | `GET/POST /api/user/{id}/transaction`, `GET/DELETE /api/user/{id}/transaction/{tid}` (DELETE = undo) |
| Articles | `GET/POST /api/article`, `GET/POST/DELETE /api/article/{id}` |
| Barcodes / tags | `GET/POST/DELETE /api/article/{id}/barcode[/{bid}]`, same for `…/tag` |
| Metrics | `GET /api/metrics`, `GET /api/user/{id}/metrics` |
| Settings | `GET /api/settings` — serves the `strichliste.yaml` values |

---

## This version vs. strichliste.org (the old website)

[strichliste.org](https://www.strichliste.org/) still describes the
previous generation (v1.8: a React/Redux single-page frontend over the
PHP backend, "PHP 7.1 or higher"). If you arrive from there, the
differences:

**Changed in this version**

- The separate **React frontend is gone** — the backend now renders the
  complete UI itself (same look and feel, works without JavaScript).
  One application to deploy instead of two.
- **PHP 8.4+** instead of 7.1; Symfony 7.4.
- The website recommends MySQL and warns against SQLite; today
  **SQLite is a fine default for small installs**, and Postgres is the
  bundled Docker default.
- The site's install page predates containers: this repo ships a
  complete **Docker/FrankenPHP setup** (see README) as the recommended
  path; the classic tarball + nginx/Apache route still works.
- API documentation used to be a markdown file; it is now also a
  **live OpenAPI/Swagger UI** at `/api/doc` on your own instance.
- The FAQ's support channels are stale: freenode IRC no longer exists.
  Use the GitHub issue tracker instead.

**On the website but (still) missing here** — honest gap list:

- **A hosted demo of *this* version** — demo.strichliste.org runs the
  old SPA.
- **Screenshots** — neither this repository nor this document shows
  the UI; the website's screenshots show the old (visually similar)
  interface.
- **Tagged releases / downloadable tarballs of this rewrite** — the
  release workflow exists (`.github/workflows/package.yml`) but no
  release of the rewritten version has been published yet.
- **An updated website** — install instructions, FAQ and news on
  strichliste.org all describe the old version.
- The old **user-organization gallery** ("who uses strichliste") has no
  equivalent here.

**Asked about often, by design absent (both versions)**

- No login / permissions / admin role — strichliste is a digital tally
  sheet, not a banking product. Put it on a trusted network.
- No real payment processing — PayPal support is a payment *link*, not
  an API integration; money still has to be confirmed by balance.

---

## Where to go next

- **README.md** — Docker operations manual: TLS, every `.env` knob,
  backup & restore, upgrades, rollback, troubleshooting.
- **docs/Config.md**, **docs/Commands.md**, **docs/API.md** — the
  original reference docs (this document supersedes Config.md's
  defaults where they differ).
- **`/api/doc`** on your instance — interactive API documentation.
- **CONTRIBUTING**: run `make test` (API contract), `make e2e`
  (browser tests), `make lint` before sending changes.
