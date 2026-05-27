# Epic 5 — Metrics, Hardening & PHP Removal (Refinement)

Finish the API contract with the metrics/aggregation endpoints, run a full
compatibility-hardening pass, complete the integration test suite, and remove the
PHP/Symfony stack so the project is a single Go binary.

All amounts in **cents** (integers). After this epic there is no PHP reference in
the repo (only git history + `demo.strichliste.org`).

## Endpoints delivered

| Method | Path | Returns |
|--------|------|---------|
| GET | `/api/metrics` | Global metrics |
| GET | `/api/user/{userId}/metrics` | Per-user metrics |

## `GET /api/metrics`

Query param `days` (default 30). Response shape:
```json
{
  "balance": <int cents>,
  "transactionCount": <int>,
  "userCount": <int>,
  "articles": [ <Article object, depth 0>... ],
  "days": [ <day entry>... ]
}
```
- **`balance`** = `SUM(user.balance)` where `disabled = false`, or `0` if none.
- **`transactionCount`** = total transaction rows (all, incl. deleted), or 0.
- **`userCount`** = total user rows (**includes disabled** users).
- **`articles`** = all `active = true` articles, ordered `usageCount DESC`,
  serialized at **depth 0** (so `precursor` is null).
- **`days`** = `getTransactionsPerDay(days)` — see below.

### `getTransactionsPerDay(days)`
1. `begin = now - days` days; `dateBegin = begin` formatted `Y-m-d 00:00:00`.
   `end = tomorrow` (start of next day). Iterate one day at a time over the
   half-open range `[begin, end)` — this includes today (~`days + 1` entries).
2. Seed each date key `YYYY-MM-DD` with a **zero entry**:
   ```json
   { "date": "<YYYY-MM-DD>", "transactions": 0, "distinctUsers": 0,
     "balance": 0, "charged": 0, "spent": 0 }
   ```
3. Query transactions with `created >= dateBegin`, grouped by `DATE(created)`:
   - `countTransactions`, `distinctUsers = COUNT(DISTINCT user)`,
   - `amount = SUM(amount)`,
   - `countCharged = SUM(amount >= 0 ? 1 : 0)`, `countSpent = SUM(amount < 0 ? 1 : 0)`,
   - `amountCharged = SUM(amount >= 0 ? amount : 0)`,
   - `amountSpent = SUM(amount < 0 ? amount : 0)`.
4. Merge each result row into its date key, **replacing** the zero `charged` /
   `spent` scalars with objects:
   ```json
   "charged": { "amount": <amountCharged>, "transactions": <countCharged> },
   "spent":   { "amount": <amountSpent * -1>, "transactions": <countSpent> },
   "balance": <amount>, "transactions": <countTransactions>,
   "distinctUsers": <distinctUsers>
   ```
5. Return `array_values(array_reverse(entries))` — **most recent day first**.

**Critical quirk to preserve:** days with no transactions keep `charged: 0` and
`spent: 0` (scalar integers), while days with transactions render `charged` /
`spent` as **objects**. The field types are heterogeneous across entries.
`spent.amount` is stored as a positive number (`amountSpent * -1`).

`DATE(created)` grouping: implement with Postgres `DATE(created)` /
`to_char(created,'YYYY-MM-DD')` so the result keys match the seeded
`YYYY-MM-DD` keys exactly. Watch timezone: match the server-local wall-clock
behavior used elsewhere.

## `GET /api/user/{userId}/metrics`

`findByIdentifier` (numeric id **or** name); not found ⇒ `UserNotFound(userId)`
404. Response:
```json
{
  "balance": <int cents>,
  "articles": [
    { "article": <Article, depth 0>, "count": <int>, "amount": <int> }, ...
  ],
  "transactions": {
    "count": <int>,
    "outgoing": { "count": <int>, "amount": <int> },
    "incoming": { "count": <int>, "amount": <int> }
  }
}
```
- **`balance`** = the user's current balance.
- **`articles`** = per-article aggregation over the user's transactions where
  `article IS NOT NULL`: `count = COUNT(article rows)`,
  `amount = SUM(t.amount) * -1`, grouped by article, ordered by count DESC.
  Each article serialized at depth 0.
- **`transactions.count`** = COUNT of the user's transactions with
  `deleted = false`.
- **`outgoing`** = aggregate (`count`, `amount = SUM(amount)`) over the user's
  non-deleted transactions that **have a `recipientTransaction`** (money this
  user sent). `amount` is the raw sum (not negated).
- **`incoming`** = same but transactions that **have a `senderTransaction`**
  (money this user received).
- If a user has no outgoing/incoming rows, that block is `{count: 0, amount: 0}`
  (PHP reads a null result and casts to 0 — replicate as zeros, not null).

## Error catalog (additions for Epic 5)

| Exception (Go id) | HTTP | Message |
|---|---|---|
| `UserNotFoundException` | 404 | `User '%s' not found` (reused) |

(No new exception types; metrics only raises `UserNotFound`.)

## Hardening pass (cross-cutting, whole API)

A dedicated sweep to lock in true compatibility across Epics 1–4 as well:
- **Error envelope** consistency: every endpoint returns
  `{"error":{class,code,message}}` with the right status and verbatim messages
  (including the retained misspellings/trailing periods noted in earlier epics).
- **`Cache-Control`** header on all responses; CORS behavior on `/api`.
- **Date/time formatting** `Y-m-d H:i:s` everywhere; timezone consistency
  between stored timestamps, serialized output, and `DATE()` grouping.
- **Number/`(int)` casts**: all `count`/`amount` fields are JSON integers, never
  strings or floats (Postgres `SUM`/`COUNT` come back as strings/decimals in raw
  drivers — cast explicitly).
- **Pagination & ordering** parity for every list endpoint; pin orderings that
  PHP left implicit via tests / demo comparison.
- **JSON null vs absent**: nullable fields (`email`, `article`, `precursor`,
  `sender`, `recipient`, `updated`) serialize as `null`, matching PHP.
- **Settings-driven behavior**: boundaries, undo timeout/delete, stale period
  honored end-to-end.
- Review all "known/accepted divergences" from Epics 1–4 and confirm each is
  intentional and documented.

## Complete the integration test suite

- End-to-end coverage of all ~30 endpoints (Epics 1–5) against a real Postgres.
- A **golden/contrast harness**: for a representative set of requests, compare Go
  responses against `https://demo.strichliste.org/api/` where reachable, and
  against fixtures derived from the PHP source where not.
- Metrics-specific tests: `days` boundary (0, 1, 30, large), the heterogeneous
  `charged`/`spent` scalar-vs-object quirk, reverse ordering, distinct-user
  counts, per-user article aggregation, outgoing/incoming with transfers, and
  the zero-result casts.
- A full smoke flow: create users → deposit → buy articles → transfer → tag →
  revert → read metrics, asserting balances and aggregates reconcile.

## PHP / Symfony removal

Once Go parity is verified and tests are green, delete the legacy stack:
- Directories: `src/`, `config/` (keep/relocate `strichliste.yaml`),
  `bin/`, `public/`, `var/`, `vendor/`, `migrations/`, `tests/` (PHP),
  `examples/` and `contrib/` if PHP-specific.
- Files: `composer.json`, `composer.lock`, `symfony.lock`, `phpunit.dist.xml`,
  `.env`/`.env.test` if Symfony-specific (replace with Go-oriented env docs).
- **Preserve**: `strichliste.yaml` (relocated to where the Go binary loads it),
  `LICENSE`, `docs/` (update), `.github/` (rewrite CI for Go build/test),
  `README.md` (rewrite for the Go binary: build, env vars, run).
- Update `.gitignore`, `.editorconfig` as needed for Go.
- Final repo: Go module + binary, `strichliste.yaml`, docs, CI — no PHP.

Do the removal as its own commit(s) so it is easy to review and revert.

## Testing

**Unit tests (written and executed):**
- `getTransactionsPerDay` entry seeding, merge, scalar-vs-object branch, reverse
  ordering, `spent.amount` sign flip.
- Aggregation SQL result → typed-int casting helpers.
- Per-user article aggregation and outgoing/incoming classification logic.

**Integration tests (HTTP, real Postgres):** as in the suite section above.

## Definition of done (Epic 5)

- Both metrics endpoints implemented, matching PHP shapes/quirks exactly.
- Hardening pass complete; full integration suite green against real Postgres;
  representative responses cross-checked against the demo API.
- All PHP/Symfony artifacts removed; repo builds and runs as a single Go binary;
  CI runs Go build + tests; README/docs updated.
- Browser check: `/api/metrics` and `/api/user/{id}/metrics` return correct data;
  the whole API serves from the Go binary with no PHP present.
- Small, frequent commits; removal isolated in its own commit(s).

## Known / accepted divergences (preserved quirks)

- `days` entries have heterogeneous `charged`/`spent` types: scalar `0` on empty
  days, objects on active days.
- `userCount` includes disabled users; `metrics.balance` excludes them.
- `spent.amount` (global days) and per-user article `amount` are sign-flipped
  (`* -1`) relative to the raw stored amounts.
- Empty outgoing/incoming blocks render as `{count:0, amount:0}`.
