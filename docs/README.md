# Strichliste2 Documentation

Strichliste is a single Symfony application that serves both:

- The **server-rendered Twig UI** at user-facing routes (`/`, `/user/active`,
  `/articles/active`, `/split-invoice`, `/metrics`, `/search-results`,
  `/settings`). The UI is operable without JavaScript. JS layers (Stimulus
  controllers + Turbo) are progressive enhancement only.
- The **REST API** at `/api/*` for third-party clients. The API contract is
  frozen — see `API.md` (if maintained externally) for the JSON shape.

## Database

Default development setup is **Postgres via docker-compose** at the
repository root. SQLite is the alternative for quick local hacks without
Docker. Tests always use SQLite (configured via `.env.test`).

See `.env` for the active `DATABASE_URL` and the SQLite fallback comment.

## Configuration

Application settings live in `config/strichliste.yaml` (currency, idle
timeout, deposit/dispense step buttons, account boundaries, PayPal, etc.).
The same parameters back the Twig UI and the `/api/settings` endpoint.

See `Config.md` for the parameter reference.

## Commands

CLI commands (import, cleanup, status) live under `src/Command/`. See
`Commands.md` for usage.
