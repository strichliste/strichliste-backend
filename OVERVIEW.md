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

![The user list — the screen the kiosk shows by default](docs/screenshots/user-list.png)

![A user's page with the buy tab open](docs/screenshots/user-buy.png)

A public demo of the *older* version runs at
[demo.strichliste.org](https://demo.strichliste.org); the project
website is [strichliste.org](https://www.strichliste.org/) (also
describing the older version — see
[the comparison section](#this-version-vs-strichlisteorg-the-old-website)).

**Quick orientation:**

| You want to… | Read |
| --- | --- |
| Decide whether your club/space should use this | [For decision-makers](#for-decision-makers-no-it-knowledge-needed) |
| Try it on your machine in 5 minutes | [Getting started](#getting-started-in-five-minutes) |
| Install it permanently for your space | [Running in production](#running-in-production--installation) |
| Build the actual fridge kiosk (Pi, scanner, …) | [Building the kiosk](#building-the-kiosk) |
| Tune currency, limits, buttons, PayPal… | [Configuration reference](#configuration-reference-configstrichlisteyaml) |
| Build a client / script against the API | [The REST API](#the-rest-api) |
| Understand the code | [How the application works](#how-the-application-works) |

---

## For decision-makers (no IT knowledge needed)

This section is for the treasurer or board member who has to decide —
everything below it is for the people who set things up.

- **Cost & license.** strichliste is free, open-source software (MIT
  license). There is nothing to buy, no subscription, and no vendor —
  but also no paid support; help comes from the
  [GitHub issue tracker](https://github.com/strichliste).
- **What you need.** A small computer that is always on (a ~60 €
  Raspberry Pi or any old PC/NAS) and a screen or tablet by the fridge
  showing the strichliste page. Many spaces run both on one device.
  A volunteer with basic server experience needs an evening to set it
  up and should plan a few hours a year for updates.
- **Internet is not required.** strichliste runs entirely on your local
  network. Internet only matters for two optional conveniences:
  automatic TLS certificates and PayPal top-up links.
- **If the power fails**, nothing already booked is lost — every
  transaction is stored immediately. At most, the booking someone was
  in the middle of typing is gone.
- **The trust model is the paper list's.** Anyone in the room can book
  on any account — that is a feature, not an oversight. Consequently:
  **never make strichliste reachable from the internet** (your setup
  person knows this as "LAN/VPN only"; it's also spelled out below).
- **The money is play-pretend until you reconcile it.** Pressing
  "deposit 10 €" does not check that 10 € went into the cash box. The
  metrics page shows the sum of all balances so the treasurer can
  compare books against the box. The same applies to PayPal top-ups:
  strichliste credits the account when the member returns from PayPal,
  but it cannot verify the payment really completed — check your PayPal
  account against the books.
- **Three settings the board should decide, not default:**
  - `account.boundary.lower` — the credit line. The default is
    **−200 € per member**. Thirty members can owe the club 6,000 €.
    Pick a number you can live with.
  - `payment.undo.delete` — leave it `false` (the default) so undone
    transactions stay visible in the history. Setting it to `true`
    makes bookings silently erasable, which your cash audit
    (Kassenprüfung) will not appreciate.
  - Backups — insist that whoever installs it sets up the nightly
    backup from the README *before* go-live, and shows you a restore
    once.
- **Member data (GDPR).** strichliste stores names, optional e-mail
  addresses, and a complete log of who booked what and when — visible
  to everyone at the kiosk. That is personal data under the GDPR: tell
  your members, mention it in your privacy notice, and decide a
  retention period (the `app:retire-data` command deletes transactions
  older than X — but check your bookkeeping retention duties before
  deleting financial records).
- **Maturity, honestly:** this is the actively developed rewrite of a
  project that has run in dozens of spaces for years. The rewrite has
  **no tagged release yet**, and the public demo/website still show the
  previous version (which looks nearly identical).
- **The note to hand your tech volunteer:** *"Please install the Docker
  production setup from the README on the box by the fridge, LAN-only,
  set up the nightly backup and show me a restore, set
  `account.boundary.lower` to the value the board picked, leave
  `payment.undo.delete` off, and show me the metrics page."*

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
  1.50 €") and buy with one tap. On a user's page, a USB barcode
  scanner works without drivers or configuration — see
  [Building the kiosk](#building-the-kiosk) for exactly how.
- **Undo** — every transaction shows an undo button for a configurable
  grace period (default: 5 minutes), so a slipped finger is not a
  treasury incident.
- **Transfers** — send money from one account to another, with an
  optional comment ("thanks for the pizza").
- **Split invoice** — one person paid for the group order; this page
  splits the total across any set of members in one step.
- **Statistics** — a metrics page for the whole system (sum of all
  balances for cash-box reconciliation, transaction volume, top
  articles, activity charts) and a personal metrics page per user.
- **PayPal top-up** *(optional)* — users can settle their balance via
  paypal.me-style payment links, with a configurable percentage fee
  passed on to the payer. (A convenience link, not a verified payment
  integration — see the decision-makers section.)
- **Search** — find users and articles from the header on every page.
- **Kiosk-friendly UI** — touch targets are sized for fingers, a
  dark theme follows the device's dark-mode preference (nice for a
  screen that runs 24/7), successful bookings play a short sound, and
  an idle timer returns the screen to the user list.
- **Localization** — the interface ships in **English and German** and
  follows the configured currency (name, symbol, ISO code) everywhere.
- **REST API + OpenAPI docs** — everything above is also scriptable;
  see [The REST API](#the-rest-api).

Everything monetary in strichliste — config values, API payloads,
database rows — is an **integer number of cents**. `1.50 €` is `150`.
There is no floating point money anywhere.

---

## How the application works

*(This section and everything below is for developers and
administrators.)*

strichliste is a **single Symfony 7.4 application** (PHP 8.4+). One
process serves two faces:

1. **The web UI** — server-rendered Twig pages at the user-facing
   routes (`/`, `/user/active`, `/user/{id}`, `/articles`,
   `/split-invoice`, `/metrics`, `/search-results`). This is the
   primary interface, designed for a wall-mounted kiosk:
   - It is **fully operable without JavaScript** — every action is a
     real HTML form, so it stays usable on old donated tablets.
     JavaScript (Stimulus controllers + Turbo) only layers comfort on
     top: snappier navigation, the barcode listener, the idle timer.
   - The look and feel matches the classic strichliste interface that
     long-time users know from the React version.
2. **The legacy REST API** at `/api/*`, kept for existing third-party
   clients (Android apps, kiosk hardware, space-automation scripts).
   The API contract is **frozen**: JSON shapes are byte-compatible with
   strichliste v1.8 and pinned by an extensive test suite
   (`tests/Controller/Api/`).

The UI and API can be used at the same time — they share the same
services and database transactions. Note there is no push channel: a
kiosk screen does not live-update when a script books via the API; the
page refreshes on navigation or via the idle timer.

### OpenAPI / Swagger

The API is documented as an OpenAPI 3 specification, served by the
application itself:

- **`/api/doc`** — interactive Swagger UI (browse endpoints, try
  requests against your own instance — mind that "Try it out" executes
  *real* bookings).
- **`/api/doc.json`** — the raw OpenAPI document, ready for code
  generators or Postman/Insomnia import.

The spec is hand-maintained in `config/packages/nelmio_api_doc.yaml`
(deliberately not generated from code, so the frozen contract can't
drift by accident). A prose version with more request/response
examples lives in `docs/API.md`.

### Code map

| Path | What lives there |
| --- | --- |
| `src/Controller/Ui/` | The Twig UI controllers (users, articles, transactions, split invoice, metrics, search, PayPal). |
| `src/Controller/Api/` | The frozen JSON API controllers, one per resource. |
| `src/Service/` | Business logic shared by both: `TransactionService` (balance math, boundaries, undo, locking), `UserService`, `ArticleService`, `MetricsService`, `SettingsService`, `MoneyParser`. |
| `src/Entity/` + `src/Repository/` | Doctrine entities (User, Article, Transaction, Barcode, ArticleTag) and queries. |
| `src/Serializer/` | Produces the exact legacy JSON shapes for `/api/*`. |
| `src/EventSubscriber/` | Cross-cutting plumbing: the API error envelope, JSON request-body handling, locale. |
| `src/Command/` | CLI tools — see [Console commands](#console-commands). |
| `config/strichliste.yaml` | All application settings — see the [reference](#configuration-reference-configstrichlisteyaml). |
| `templates/`, `assets/` | Twig templates, Stimulus controllers, CSS (no build step — AssetMapper + importmap). |
| `migrations/` | Database schema migrations (applied automatically in Docker). |
| `tests/` | PHPUnit suite pinning the API contract + Playwright end-to-end suite for the UI. |

### Database and concurrency

Storage goes through Doctrine ORM/DBAL, so the database is a
connection-string choice, not a code choice. **SQLite**, **MySQL /
MariaDB** and **PostgreSQL** are all supported and exercised in CI.

Concurrent bookings are safe: every balance change runs in a database
transaction that takes row locks on the involved users (in id order, so
two simultaneous transfers can't deadlock). On SQLite that lock is
effectively the whole database file — which is why the rule of thumb
is:

- **SQLite** — perfect for a single kiosk in a small space; zero
  administration, one file to back up.
- **PostgreSQL / MariaDB** — pick one of these when several devices
  write at once or the instance is long-lived and busy. (The bundled
  Docker setup ships Postgres by default.)

---

## Getting started in five minutes

With a current Docker (Engine 25+, Compose v2.30+ — needed for the
`--wait` semantics and compose profiles the setup uses):

```bash
git clone https://github.com/strichliste/strichliste-backend.git
cd strichliste-backend
docker compose up -d --build --wait
```

Open **https://localhost** (accept the one-time certificate warning, or
run `make tls` to trust the local CA). You get a dev environment with
Postgres, live code reload and the database schema already migrated.
Add a user, add an article under *Article List*, buy it — that's the
whole loop.

Non-interactive smoke test (also works for uptime monitoring later):

```bash
curl -sk https://localhost/api/settings   # → {"settings": …} = it works
```

To wipe the dev data and start fresh: `docker compose down -v`
(deletes the dev database volume), then `up` again.

No Docker? With PHP ≥ 8.4 and Composer installed:

```bash
echo 'DATABASE_URL="sqlite:///%kernel.project_dir%/var/dev.db"' > .env.local
composer install
php bin/console doctrine:migrations:migrate
php bin/console importmap:install
php -S 127.0.0.1:8000 -t public   # or: symfony serve
```

(Reset = delete `var/dev.db` and re-run the migrations.)

---

## Running in production / installation

> The short version: **use Docker**, set two values in `.env`, run one
> command. The [README](README.md#run-with-docker-recommended) is the
> full operations manual (TLS options, every knob, backup & restore,
> upgrades, rollback); this section is the orientation.

**Security first:** everything — the UI, `/api/*`, and the Swagger UI
at `/api/doc` — is unauthenticated by design. Keep the published ports
(`HTTP_PORT`, `HTTPS_PORT` tcp and `HTTP3_PORT` udp) restricted to your
LAN/VPN at the firewall, or put a reverse proxy with HTTP basic auth in
front. Do not port-forward strichliste to the internet.

### Docker (recommended)

The repository ships a production-grade container setup modeled on
[dunglas/symfony-docker](https://github.com/dunglas/symfony-docker):
FrankenPHP (Caddy + PHP 8.5) running the app in **worker mode** —
Symfony boots once and stays resident, which a Raspberry-Pi-class kiosk
box appreciates. The image is multi-arch (works on arm64, e.g.
Pi 4/5 with a 64-bit OS).

1. Edit `.env`:
   - set a unique `APP_SECRET` (`openssl rand -hex 32`),
   - uncomment `COMPOSE_FILE=compose.yaml:compose.prod.yaml` (this pins
     plain `docker compose` commands to production — without it, a
     casual `docker compose up` later loads the *development* override),
   - optionally set `SERVER_NAME`:
     - a real hostname → automatic Let's Encrypt certificates (note:
       this requires the box to be reachable from the public internet
       on ports 80/443, which contradicts the LAN-only advice — on an
       internal install, stay with the local CA or terminate TLS at
       your own proxy),
     - `localhost` (default) → self-signed local CA,
     - `":80"` → plain HTTP — for LAN-IP kiosks, **or when an existing
       nginx/traefik terminates TLS in front**: then also remap
       `HTTP_PORT`/`HTTPS_PORT`/`HTTP3_PORT` to free internal ports and
       uncomment `TRUSTED_PROXIES` in `.env` so client IPs and the
       https scheme survive the hop.

   (Putting the secret in `.env` is fine only because the image is
   built and run on the same box — `.env` gets baked into the image.
   Never push such an image to a registry; for registry-based deploys
   pass `APP_SECRET` as a real environment variable instead. Note that
   `.env` is git-tracked, so your local edits must survive `git pull`.)
2. Start it:

   ```bash
   docker compose -f compose.yaml -f compose.prod.yaml up -d --build --wait
   # or: make prod
   ```

The container **waits for the database and applies migrations on every
boot** — first install and upgrades are the same command (`git pull &&
make prod`). Health checks, restart policy and log rotation are
preconfigured. `make prod` also re-pulls the base images so PHP/Caddy
security patches actually arrive; a code-only rebuild would not.

**Choosing the database is a pure `.env` decision** — bundled Postgres
by default; or set `DATABASE_URL` to any Doctrine DSN (SQLite file,
external MariaDB/MySQL or Postgres) and switch the bundled Postgres off
with `COMPOSE_PROFILES=` (empty). The image contains all three drivers.

Good to know, day 2:

- **When something breaks, the page to open is
  `docker compose logs app`** — access log, PHP errors and the
  entrypoint's progress all go to container output (rotated at
  3 × 10 MB); nothing hides in files inside the container.
- **External uptime monitoring:** probe `GET /api/settings` (cheap,
  unauthenticated, 200 + JSON). The built-in Docker healthcheck only
  gates startup ordering and `docker compose ps` output.
- **Sizing:** a 1 GB Pi-class box runs the whole stack. On such boxes
  uncomment the memory-limit and `num_threads` knobs in
  `compose.prod.yaml`. Disk usage is the database plus ≤ 60 MB of
  rotated logs.
- **There is no prebuilt image on a registry** — NAS/Portainer users
  build with `docker build --target frankenphp_prod` and push to their
  own registry (mind the baked-`.env` warning above).
- **Getting data out** (migration away, audits): the README backup
  commands give you a plain SQL dump or a single SQLite file, and
  everything is also readable as JSON via `/api/*`. There is no
  dedicated export command.
- **Before you rely on it for real money, read
  [Backup, upgrades, rollback](README.md#backup-upgrades-rollback) in
  the README.** It is short, tested, and the difference between
  "restore from last night" and a shoebox full of receipts.

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
   Errors land in your PHP-FPM/web-server error log (the app does not
   write log files of its own).

---

## Building the kiosk

The server is half the job — the other half is the screen by the
fridge. strichliste's part is ready (touch-sized buttons, no-JS
fallback for weak hardware, dark theme via `prefers-color-scheme`,
idle return to the user list); the OS side is yours:

- **Hardware**: a Pi 4/5 with a 64-bit OS runs server *and* fullscreen
  browser at once (4 GB RAM comfortably; 2 GB if the server runs
  elsewhere). Older 32-bit Pis: run the server elsewhere and use the Pi
  only as a browser terminal. Any old tablet pointed at the server
  works too — the UI works without JavaScript, so even ancient browsers
  hold up.
- **Kiosk browser**: the usual recipe is Chromium in kiosk mode:

  ```bash
  chromium --kiosk --noerrdialogs --disable-pinch \
    --autoplay-policy=no-user-gesture-required \
    https://your-server/user/active
  ```

  plus `unclutter` to hide the cursor and disabling screen blanking.
  Ready-made kiosk distributions (FullPageOS, porteus-kiosk, a Wayland
  `cage` session) all work — strichliste is just a web page.
  The `--autoplay-policy` flag is what allows the booking sound;
  `--force-dark-mode` forces the dark theme on a screen that runs at
  night. There is no kiosk lockdown in strichliste itself — preventing
  people from browsing elsewhere is the kiosk browser's job.
- **On-screen keyboard**: strichliste does not ship one; for adding
  users on a pure touchscreen, enable the OS keyboard (`squeekboard`,
  `onboard`, or the tablet's native one).
- **Barcode scanner**: any scanner that presents as a **USB/Bluetooth
  HID keyboard** works — scans are recognized by their fast keystroke
  burst (keys < 200 ms apart, ending with Enter, ≥ 3 characters), so no
  driver or configuration is needed. Precisely:
  - Scanning works **on a user's detail page only** — the flow at the
    fridge is: tap your name, scan the bottle. (Set
    `article.autoOpen: true` so the page opens on the buy tab — that's
    the intended scanner-kiosk mode.)
  - An unknown barcode shows an "unknown barcode" message and changes
    nothing.
  - To teach an article its barcode: open the article's edit page,
    click the barcode field, scan into it.
  - Serial (RS-232/USB-CDC) scanners and very slow Bluetooth scanners
    that type with > 200 ms between keys are **not** recognized — wrap
    them with a small script against the API instead (recipe in
    [The REST API](#the-rest-api)).
- **Offline behavior**: there is none — no service worker, no offline
  queue. If the kiosk loses the server, the browser shows an error page
  until it reconnects. The practical mitigation is the recommended
  setup anyway: run server and kiosk on the same box (SQLite), and the
  Wi-Fi stops mattering.
- **NFC/RFID member cards**: not built in. A popular DIY pattern: card
  reader → look up the member (`GET /api/user/search?query=…`) → point
  the kiosk browser at `/user/{id}`.
- **Space dashboard**: `GET /api/metrics` serves the global numbers
  (sum of balances, transaction counts, top articles) as JSON — see the
  schema at `/api/doc`.
- **Events/MQTT**: strichliste emits no webhooks or MQTT messages (see
  [the API section](#the-rest-api) for the polling pattern).

---

## Configuration reference (`config/strichliste.yaml`)

All application-level behavior is configured in **one file**,
`config/strichliste.yaml`, under the `parameters.strichliste` key. The
same values drive the web UI **and** are exposed verbatim to API
clients via `GET /api/settings`.

Applying changes:

- **Bare metal**: edit the file, then `php bin/console cache:clear`.
- **Docker**: bind-mount your copy into the `app` service — the
  entrypoint recompiles the cache on every boot, so a restart applies
  it. See README →
  ["Settings without rebuilding"](README.md#run-with-docker-recommended)
  for the exact snippet and **which compose file to put it in** (your
  own extra file — not `compose.override.yaml`, which is dev-only).

Two recurring datatypes:

- **money** — always integer **cents**: `1000` = 10.00 €.
- **timeperiod** — a PHP relative date string like `'5 minute'`,
  `'10 day'`, `'2 week'` ([format reference](https://www.php.net/manual/en/datetime.formats.relative.php)).

### `article`

| key | type | default | what it does |
| --- | --- | --- | --- |
| `enabled` | bool | `true` | Master switch for the article system. Off: no *Buy* tab on user pages, no article routes in the UI. |
| `autoOpen` | bool | `false` | When on, a user's page opens directly on the *Buy* tab (instead of the transaction history) — the intended mode for scanner kiosks. |

### `common`

| key | type | default | what it does |
| --- | --- | --- | --- |
| `idleTimeout` | int (ms) | `30000` | After this many milliseconds without input, the kiosk returns to the user list — so the screen is never left on someone's account page. `0` disables. (Needs JavaScript; without JS there is simply no auto-return.) |

### `paypal`

| key | type | default | what it does |
| --- | --- | --- | --- |
| `enabled` | bool | `false` | Show a PayPal top-up option on user pages. **Change `recipient` before enabling** — the shipped placeholder is a stranger's address. |
| `recipient` | string | `foo@bar.de` (placeholder!) | The receiving PayPal account (e-mail address). |
| `fee` | int (percent) | `0` | Percentage added **on top** of the chosen amount and paid by the user, so PayPal's cut doesn't drain the cash box. Example: top-up 10 €, `fee: 3` → user pays 10.30 €, account is credited 10 €. |

Note: the account is credited when the member returns from PayPal via a
signed, single-use return link. strichliste does **not** verify with
PayPal that the money arrived — reconcile your PayPal account against
the books.

### `user`

| key | type | default | what it does |
| --- | --- | --- | --- |
| `stalePeriod` | timeperiod | `'10 day'` | Users with no transaction within this period are moved to the *inactive* tab (they are hidden, not deleted, and return on their next booking). Equivalent API filter: `GET /api/user?active=false`. |

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
| `upper` | money or `false` | `20000` | Maximum balance (200 €). Transactions that would exceed it are rejected. `false` = no limit. |
| `lower` | money or `false` | `-20000` | Minimum balance (−200 €) — i.e. **the credit line you extend to every member**. Decide this consciously before go-live. `false` = no limit. |

### `payment.undo`

| key | type | default | what it does |
| --- | --- | --- | --- |
| `enabled` | bool | `true` | Show an undo button on recent transactions (in the web UI). |
| `delete` | bool | `false` | `false`: the undone transaction stays in the history, marked as reverted. `true`: undo removes it from the database entirely — **bad for auditability; leave it off if a treasurer ever has to check the books.** |
| `timeout` | timeperiod or `false` | `'5 minute'` | How long a transaction stays undoable in the UI. `false` = forever. |

Note: these settings govern the **web UI**. The legacy API's
`DELETE …/transaction/{id}` honors the frozen v1.8 contract and reverts
regardless of `enabled`/`timeout` — one more reason the API must only
be reachable from a trusted network.

### `payment.boundary`

| key | type | default | what it does |
| --- | --- | --- | --- |
| `upper` | money or `false` | `15000` | Largest single transaction (150 €) — guards against an accidental extra zero. `false` = no limit. |
| `lower` | money or `false` | `-2000` | Largest single deduction (−20 €). `false` = no limit. |

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
| `app:import <file>` | Import a **strichliste 1** `database.sqlite`. **Wipes the target database first** (all existing users, transactions *and* articles are deleted) — only run it on a fresh install. Imports users + transactions; strichliste 1 had no articles, so the product list is re-entered by hand. |
| `app:user:status <user> <true\|false>` | Enable/disable an account by name or id. |
| `app:user:cleanup --days/--months/--years [--minBalance --maxBalance --confirm]` | Bulk-disable accounts inactive for longer than the interval (optionally only within a balance range). |
| `app:retire-data --days/--months/--years [--confirm]` | **Delete** transactions older than the interval — the data-privacy lever. Check your bookkeeping retention duties first. |
| `app:ldapimport --host … --bindDn … --baseDn …` | Create/update users from an LDAP directory (cron-able). Note: needs the `symfony/ldap` package, which is a dev dependency — for production use run `composer require symfony/ldap` (not available in the stock Docker image). |
| `cache:clear` | Apply `strichliste.yaml` changes (bare metal; the Docker entrypoint does this on boot). |
| `doctrine:migrations:migrate` | Apply schema migrations (automatic in Docker). |

Importing into Docker — the file must be inside the container:

```bash
docker compose cp database.sqlite app:/tmp/old.sqlite
docker compose exec app php bin/console app:import /tmp/old.sqlite
```

There is **no CSV / paper-list importer**. The practical route is a
small shell loop over the API: `POST /api/user` per member, then
`POST /api/user/{id}/transaction` with the opening balance.

Data export: see the backup commands in the README (SQL dump / SQLite
file), or read everything as JSON via the API.

---

## The REST API

The `/api/*` endpoints speak the **strichliste v1.8 contract**,
unchanged, so existing clients keep working. Essentials:

- Amounts are **integer cents**; timestamps are `YYYY-MM-DD HH:MM:SS`.
- Request bodies may be JSON or form-encoded. **JSON bodies require
  `Content-Type: application/json`** — without it the body is silently
  ignored and you get a confusing "parameter missing" error.
- Errors use one envelope, where `class` is the PHP exception class
  name clients switch on:

  ```json
  {"error": {"class": "App\\Exception\\TransactionBoundaryException",
             "code": 400, "message": "Transaction boundary reached"}}
  ```

- **Pagination is uneven (frozen contract):** `/api/transaction`,
  `/api/user/{id}/transaction` and `/api/article` accept
  `?limit=…&offset=…` with a **default limit of 25** — forget `limit`
  and you silently see only 25 rows. `GET /api/user` ignores both and
  always returns **all** users. The `/search` endpoints accept `limit`
  only.
- **No idempotency mechanism**: retrying a timed-out
  `POST …/transaction` books twice. Reconcile via
  `GET /api/user/{id}/transaction` after network errors.
- **No webhooks/push**: to watch for new bookings, poll
  `GET /api/transaction` (oldest-first; remember the highest seen `id`
  or use `offset` = last seen count).
- User routes accept a numeric id **or the exact name**
  (`GET /api/user/alice`); the transaction routes take numeric ids
  only.
- CORS for `/api/*` is deliberately wide open (`allow_origin: '*'` in
  `config/packages/nelmio_cors.yaml`) so browser clients on other
  origins work; there are no rate limits. Both are consistent with the
  trusted-network model — and more reasons not to expose it publicly.
- There is **no authentication** — the API trusts the network like the
  kiosk trusts the room.

The two calls every integration makes:

```bash
# deposit 1.00 € with a comment
curl -X POST http://server/api/user/4/transaction \
  -H 'Content-Type: application/json' \
  -d '{"amount": 100, "comment": "cash box"}'

# buy an article (server computes the price; amount must be omitted
# or negative — sending a positive amount with articleId is rejected)
curl -X POST http://server/api/user/4/transaction \
  -H 'Content-Type: application/json' \
  -d '{"articleId": 3, "quantity": 1}'
# → {"transaction": {"id": …, "amount": -150, "article": {…}, …}}
```

The scanner-script recipe (e.g. for a serial scanner or vending
machine): `GET /api/article?barcode=<scan>` → take `articles[0].id` →
`POST /api/user/{id}/transaction` with `{"articleId": …}`.

Resource overview (full, browsable detail at **`/api/doc`**; prose
examples in `docs/API.md`):

| Resource | Endpoints |
| --- | --- |
| Users | `GET/POST /api/user`, `GET/POST /api/user/{id}` (id or exact name), `GET /api/user/search` |
| Transactions | `GET /api/transaction` (global list), `GET/POST /api/user/{id}/transaction`, `GET/DELETE /api/user/{id}/transaction/{tid}` (DELETE = undo/revert) |
| Articles | `GET/POST /api/article` (filters: `barcode`, `active`, …; `count` always reflects active articles), `GET/POST /api/article/{id}`, `DELETE /api/article/{id}` (soft delete: deactivates), `GET /api/article/search` |
| Barcodes / tags | `GET /api/barcode`, `GET /api/tag`, and per article `GET/POST …/barcode`, `GET/DELETE …/barcode/{bid}` (same shape for `…/tag`) |
| Metrics | `GET /api/metrics` (system-wide: sum of balances, counts, top articles), `GET /api/user/{id}/metrics` |
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
- **Tagged releases / downloadable tarballs of this rewrite** — the
  release workflow exists (`.github/workflows/package.yml`) but no
  release of the rewritten version has been published yet.
- **An updated website** — install instructions, FAQ and news on
  strichliste.org all describe the old version.
- The old **user-organization gallery** ("who uses strichliste") has no
  equivalent here.
- The old site documented serial/Bluetooth scanner setups in more
  depth; this version's built-in support is HID-keyboard scanners only
  (see [Building the kiosk](#building-the-kiosk)).

**Asked about often, by design absent (both versions)**

- No login / permissions / admin role — strichliste is a digital tally
  sheet, not a banking product. Put it on a trusted network.
- No real payment processing — PayPal support is a payment *link*, not
  a verified API integration.
- No webhooks/MQTT events — integrations poll the API.

---

## Where to go next

- **README.md** — Docker operations manual: TLS, every `.env` knob,
  backup & restore, upgrades, rollback, troubleshooting.
- **docs/Config.md**, **docs/Commands.md**, **docs/API.md** — the
  original reference docs (this document supersedes Config.md's
  defaults where they differ).
- **`/api/doc`** on your instance — interactive API documentation.
- **`make e2e`** — the Playwright suite clicks through every UI flow;
  reading `tests/e2e/` doubles as a tour of what the app can do.
- **`contrib/ansible/`** — a stale community playbook targeting the
  *previous* version (Debian Buster, PHP 7) — prefer the Docker setup.
- **Contributing**: run `make test` (API contract), `make e2e`
  (browser tests), `make lint` before sending changes.
