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

## Run locally

```
composer install
php bin/console doctrine:migrations:migrate
php bin/console importmap:install
APP_ENV=dev APP_DEBUG=1 symfony serve
```

Open `http://127.0.0.1:8000`.

For production:

```
composer install --no-dev --optimize-autoloader
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
