# Multi-Agent Code Review — strichliste (symfony-ux rewrite)

**Branch:** `review/multi-agent-2026-06-15`
**Date:** 2026-06-15
**Method:** Five independent specialist review agents (frontend, backend, test engineering,
UX/user-testing, security & API-contract), findings collected and triaged below.

Hard constraints the review judged against:
1. The `/api/*` JSON contract is **frozen byte-identical** for legacy Android clients
   (pinned by `tests/Controller/Api/`).
2. Every page must work **without JavaScript** (kiosk-grade progressive enhancement).
3. Money is **integer cents** server-side.

---

## Triage summary

| # | Finding | Sev | Source(s) | Disposition |
|---|---------|-----|-----------|-------------|
| 1 | API `DELETE` transaction ignores `{userId}` scope + undo gate | HIGH | backend, security | **Decision needed** (frozen contract) |
| 2 | Pessimistic locks are no-ops on SQLite; double-spend window | CRIT | backend, test | **Decision needed** (architectural) |
| 3 | Committed `APP_SECRET` + `APP_ENV=dev` in tracked `.env` | HIGH | security | **Decision needed** (secret rotation/deploy) |
| 4 | `MoneyType` (comma/dot) vs `MoneyParser` inconsistency in money inputs | CRIT | ux | **Decision needed** (touches e2e) |
| 5 | No direct service/unit tests for money logic | CRIT | test | **Fixing** (Agent A) |
| 6 | `ImportCommand` float truncation mis-books cents (+ `$count` never incremented) | HIGH | backend | **Fixing** (Agent C) |
| 7 | Missing `form_errors(form)` on create/edit forms | CRIT* | frontend | **Fixing** (Agent B) |
| 8 | `UserCard` class concatenation bug strips styling from disabled users | HIGH | frontend | **Fixing** (Agent B) |
| 9 | PayPal amount uses `type=number`; rejects comma input | MED | frontend, ux | **Fixing** (Agent B) |
| 10 | `idle` controller ignores changed `<select>` (loses recipient mid-transfer) | MED | frontend, ux | **Fixing** (Agent B) |
| 11 | `double_submit` never re-enables on network error (kiosk lockout) | MED | frontend | **Fixing** (Agent B) |
| 12 | Search `minlength` double-owns the too-short rule | MED | frontend | **Fixing** (Agent B) |
| 13 | Un-enveloped 400 on malformed `?limit=abc` (frozen error contract) | MED | security | **Decision needed** (open question) |
| 14 | Wildcard CORS over unauthenticated `/api` | MED | security | **Decision needed** |
| 15 | Unescaped `LIKE` wildcards in API search (DoS / over-match) | MED | security | **Decision needed** (frozen contract) |
| 16 | `nelmio_api_doc` served in all envs (surface disclosure) | LOW | security | **Decision needed** |
| 17 | EAGER fetch on all Transaction/Article assocs (N+1 on list paths) | LOW | backend | Backlog |
| 18 | Generic, non-actionable money-path error messages | HIGH | ux | Backlog |
| 19 | Idle auto-return has no warning/countdown | HIGH | ux | Backlog |
| 20 | No prominent undo affordance after a purchase | HIGH | ux | Backlog |

\* CRITICAL in the kiosk context: a silent re-render with no visible reason is a dead end.

---

## Disposition rationale

**Auto-fixing this pass (low risk, high value, no frozen-contract impact):**
- **Agent A — service unit tests.** The single biggest gap and exactly aligned with the
  project's testing philosophy (test services directly, not via HTTP). Pure additions under
  `tests/`, zero production-behavior change. Covers `MoneyParser`, `TransactionService`
  boundaries/atomicity, `MetricsService`, `UserService`, `AppExtension`, and extracts the
  split penny-distribution into a unit-testable service.
- **Agent B — frontend correctness & a11y.** Templates/JS only, no contract surface.
- **Agent C — `ImportCommand` money fix.** CLI import path (not the `/api` contract); a clear
  float-truncation money bug.

**Deferred to maintainer decision (Christopher):** anything that (a) changes the frozen `/api`
contract behavior (#1, #13, #15), (b) is an architectural concurrency change (#2), (c) involves
secret rotation / deployment posture (#3, #14, #16), or (d) changes money-parsing behavior the
e2e suite depends on and that can't be e2e-verified in this pass (#4). These are real and
several are serious — they are written up so they can be picked up deliberately, not silently.

---

## Full findings by area

### Backend
- **[CRIT] Pessimistic locks no-op on SQLite** — `src/Service/TransactionService.php:329-333`.
  `EntityManager::lock(PESSIMISTIC_WRITE)` emits no `FOR UPDATE` on SQLite and is unverified on
  Postgres; the negative-balance guard is read-modify-write, so concurrent debits can both pass.
- **[HIGH] Import float truncation** — `src/Command/ImportCommand.php:94`. `(int)($value*100)`
  truncates (`1.50→149`). Use `MoneyParser::majorToCents`. (`$count` also never incremented.)
- **[HIGH] API DELETE ignores ownership + undo gate** — `src/Controller/Api/TransactionController.php:104`.
  Reverts any transaction id regardless of `{userId}` or `payment.undo.*`.
- **[MED] API typed-int coercion** — raw request strings → `?int` params with no `strict_types`;
  `"abc"`→500, `"5.5"`→silent truncation.
- **[MED] API user list passes `null` `since`** — empty/incorrect active filter when
  `user.stalePeriod` is unset.
- **[MED] `RetireDataCommand` bulk DELETE** — bypasses ORM cascade; relies on SQLite FK pragma.
- **[MED] Transaction-boundary not checked on credit side of transfer.**
- **[MED] `transferBetween` silently re-signs the amount** — masks caller bugs.
- **[LOW] `getArticleReferenceCount` ignores soft-deleted rows** — in-place edit can rewrite history.
- **[LOW] EAGER fetch everywhere** — N+1 on `/api/transaction` list paths.
- **[LOW] `precursor` OneToOne unindexed.**

### Frontend
- **[CRIT] Missing `form_errors(form)`** on `articles/create`, `users/create`,
  `users/_edit_form`, `articles/edit` — form-level errors silently swallowed.
- **[HIGH] `UserCard` class concat bug** — `templates/components/UserCard.html.twig:3`
  produces `user-carduser-card--disabled`.
- **[MED] PayPal `type=number`** — `templates/users/_tab_paypal.html.twig:22` rejects comma input.
- **[MED] Search `minlength`** double-owns the too-short rule with server `tooShort`.
- **[MED] `idle` controller ignores `<select>`** — `assets/controllers/idle_controller.js:51`.
- **[MED] `double_submit` no re-enable on error** — `assets/double_submit.js:5`.
- **[MED] disabled-step `aria-describedby`** may dangle.
- **[LOW] `sound` controller removes its own node during render.**
- **[LOW] No `forced-colors` handling; `ArticleBadge` lacks a text alternative.**
- **[LOW] Duplicated `.send-form__amount` CSS block.**

### Tests
- **[CRIT] Money boundary enforcement untested** — `checkTransactionBoundary` /
  `checkAccountBalanceBoundary`.
- **[CRIT] `doTransaction`/`transferBetween`/`doSplit` atomicity & balance untested.**
- **[CRIT] `MoneyParser` has zero tests** despite intricate separator/ambiguity rules.
- **[HIGH] Penny-distribution (`distributeAmount`) untested** — private on a controller; extract.
- **[HIGH] `revertTransaction` undo-window / delete-mode untested.**
- **[HIGH] `MetricsService` shape/sign/bucketing untested.**
- **[MED] `ArticleService` precursor/in-place branch, `UserService` stale logic untested.**
- **[MED] e2e relies on `Date.now()` names against a never-reset DB.**
- **[LOW] `AppExtension` currency formatting untested; `ApiDocTest` hardcodes op count.**

### UX
- **[CRIT] Comma/dot money-input inconsistency** between `MoneyType` forms and the `MoneyParser`
  split form — potential mis-booking on a German kiosk.
- **[HIGH] Generic money-path error messages** — user can't tell why a deposit/transfer failed.
- **[HIGH] Idle auto-return with no warning/countdown** — yanks user mid-task.
- **[HIGH] No prominent undo after purchase** — only a tiny row glyph, config-gated.
- **[MED] Disabled step buttons explain nothing; recipient `<select>` unsearchable;
  no incremental filter on the user grid; failed barcode scan drops off the buy tab.**
- **[LOW] Buy pills dense (mis-tap risk); disabled-user banner doesn't point to Edit.**

### Security
- **[HIGH] API DELETE cross-user reversal** (same as backend #1).
- **[HIGH] Committed `APP_SECRET` / `APP_ENV=dev`** — forge signed PayPal returns, profiler in prod.
- **[MED] Wildcard CORS over unauthenticated `/api`.**
- **[MED] Unescaped `LIKE` wildcards in API user/article search.**
- **[MED] Un-enveloped 400 on malformed pagination params** (the open `getInt()` question).
- **[MED] API user create/update lets anyone flip `isDisabled`** (no-auth-by-design + CORS).
- **[LOW] LDAP import trusts attrs / unchecked bind / unescaped filter field.**
- **[LOW] PayPal pending nonces accumulate in session.**
- **[LOW] `nelmio_api_doc` served in prod; verbose JSON error message leak.**
- **Verified safe:** CSRF on all state-changing UI POSTs, no unsafe `|raw`, no DQL/SQL injection,
  signed+nonce'd PayPal return, server-side sign enforcement on money writes.

---

## Overall

The codebase is disciplined and contract-conscious: integer-cents money model, careful
`MoneyParser`, genuine progressive enhancement, solid CSRF/XSS/injection hygiene, and
meaningful API-contract pins. The two themes that matter most are (1) **prove the concurrency
guarantee** (the locking design assumes a working `SELECT … FOR UPDATE` that is a no-op on the
dev DB and unverified on prod) and (2) **add fast direct tests for the money logic**, which is
where a silent regression costs real money. This pass closes the test gap and the safe
correctness/UX bugs; the contract- and deploy-sensitive items are queued for a deliberate
decision.
