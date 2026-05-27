# Epic 2 — Users & Transactions (Refinement)

The core domain: user accounts and money movement. Builds on the Epic 1
foundation (Gin, GORM, models, error envelope, CORS, cache headers, settings).
Every endpoint must match the PHP contract in status, JSON shape, and business
rules. All amounts are in **cents** (integers).

PHP stays in the repo as reference until Epic 5.

## Endpoints delivered

| Method | Path | Handler intent |
|--------|------|----------------|
| GET | `/api/user` | List users (optional `active` filter) |
| POST | `/api/user` | Create user |
| GET | `/api/user/search` | Search users by name |
| GET | `/api/user/{userId}` | Get one user (by id **or name**) |
| POST | `/api/user/{userId}` | Update user |
| GET | `/api/transaction` | List all transactions (paginated) |
| POST | `/api/user/{userId}/transaction` | Create transaction(s) for a user |
| GET | `/api/user/{userId}/transaction` | List a user's transactions (paginated) |
| GET | `/api/user/{userId}/transaction/{transactionId}` | Get one transaction |
| DELETE | `/api/user/{userId}/transaction/{transactionId}` | Revert a transaction |

## Serialization contracts (exact JSON)

### User object
```json
{
  "id": <int>,
  "name": "<string>",
  "email": <string|null>,
  "balance": <int cents>,
  "isActive": <bool>,
  "isDisabled": <bool>,
  "created": "YYYY-MM-DD HH:MM:SS",
  "updated": "YYYY-MM-DD HH:MM:SS" | null
}
```
- Date format is PHP `Y-m-d H:i:s` → Go layout `2006-01-02 15:04:05` (wall clock,
  no timezone suffix). `created` is always present; `updated` may be null.
- `isActive` is **computed**, not stored (see Stale/active logic).

### Transaction object
```json
{
  "id": <int>,
  "user": <User object>,
  "quantity": <int|null>,
  "article": <Article object|null>,
  "sender": <User object|null>,
  "recipient": <User object|null>,
  "comment": <string|null>,
  "amount": <int cents>,
  "isDeleted": <bool>,
  "isDeletable": <bool>,
  "created": "YYYY-MM-DD HH:MM:SS"
}
```
- `sender` = the user of the linked `senderTransaction` (else null).
- `recipient` = the user of the linked `recipientTransaction` (else null).
- `article` uses the Article serializer (full shape lands in Epic 3; in Epic 2 a
  transaction with an article is still serialized — coordinate the Article
  serializer minimally or land it with Epic 3 and keep article=null paths tested).
- `isDeletable` is **computed** (see Deletability).

## Business logic

### Stale / active logic (UserService)
- `getStaleDateTime()`: read `user.stalePeriod` (e.g. `"10 day"`). Parse the
  PHP relative-date string into a duration; return `now - period`. If the
  setting is absent, return null.
- `isActive(user)`: if staleDateTime is null → always `true`; else
  `user.updated != null && user.updated >= staleDateTime`.
- **Timestamp behavior to replicate (GORM hooks):** on create set both
  `created` and `updated` = now; on update set `updated` = now. Consequence: any
  balance change (a transaction) bumps `updated`, which makes the user active.
  New users are active immediately. Field names are `created`/`updated` (GORM
  tags, not the default `_at` columns).

### `GET /api/user` (list)
- Query param `active`:
  - `"true"` → active users only.
  - `"false"` → inactive users only.
  - anything else / absent → all users.
- **All three branches exclude `disabled = true` users** (the PHP `findAll`
  override filters disabled). Preserve this.
- Active = `disabled=false AND updated IS NOT NULL AND updated >= since`.
- Inactive = `disabled=false AND (updated IS NULL OR updated <= since)`.
- Final sort: case-insensitive natural-order by name (`strnatcasecmp`
  equivalent). Replicate natural ordering, not plain lexicographic.
- Response: `{ "users": [ <User>... ] }` (no count).

### `POST /api/user` (create)
- `name` required → missing ⇒ `ParameterMissing('name')` 400.
- Sanitize: trim, strip control chars `\x00-\x1F` and `\x7F`.
- After sanitize: empty or `len > 64` ⇒ `ParameterInvalid('name')` 400.
- Duplicate name (`findByName`) ⇒ `UserAlreadyExists(name)` 409.
- `email` optional; if present must pass email validation and `len <= 255`,
  else `ParameterInvalid('email')` 400; stored trimmed.
- Response: `{ "user": <User> }`.

### `GET /api/user/search`
- `query` param (substring), `limit` (default 25).
- `name LIKE %query% AND disabled=false`, order by name, capped at limit.
- Response: `{ "count": <int>, "users": [ <User>... ] }` (count = size of the
  returned page, not a total).

### `GET /api/user/{userId}`
- `findByIdentifier`: if numeric → lookup by id; else → lookup by name.
- Not found ⇒ `UserNotFound(userId)` 404.
- Response: `{ "user": <User> }`.

### `POST /api/user/{userId}` (update)
- Not found ⇒ `UserNotFound` 404.
- `name`: if `len > 64` ⇒ `ParameterInvalid('name')` 400 (checked even when
  empty — preserve). If non-empty: trim + strip control chars; if changed and
  the new name already exists ⇒ `UserAlreadyExists` 409; then set.
- `email`: if present, validate + `len<=255` else `ParameterInvalid('email')`;
  set (note: update path does **not** trim email, unlike create — preserve).
- `isDisabled`: if not null, set as given.
- Response: `{ "user": <User> }`.

### Transactions — `doTransaction(user, amount, comment, quantity, articleId, recipientId)`
Wrapped in a DB transaction with row locking. Rules in order:
1. If `(recipientId || articleId)` **and** `amount > 0` ⇒
   `TransactionInvalid("Amount can't be positive when sending money or buying an article")` 400.
2. Lock+refresh sender (and recipient if given), processed in **ascending id
   order** to avoid deadlocks. Missing user ⇒ `UserNotFound` 404.
3. If `articleId`: load+lock the article; missing ⇒ `ArticleNotFound` 404;
   `!active` ⇒ `ArticleInactive` 400. Set `quantity = quantity || 1`. If
   `amount` is null ⇒ `amount = article.amount * quantity * -1`. Attach article,
   increment its `usageCount`.
4. If `recipientId`: create a paired recipient transaction with `amount * -1`,
   same article/comment, user=recipient; link sender↔recipient transactions;
   `recipient.balance += (amount*-1)`; check **account** boundary on recipient.
5. Sender: set amount; `checkTransactionBoundary(amount)`; `sender.balance +=
   amount`; check **account** boundary on sender.
6. Return the sender transaction. Response: `{ "transaction": <Transaction> }`.

`checkTransactionBoundary(amount)`:
- `amount` falsy (0 or null) ⇒ `TransactionInvalid()` ("Transaction invalid") 400.
- `> payment.boundary.upper` ⇒ `TransactionBoundary(amount, upper)` 400.
- `< payment.boundary.lower` ⇒ `TransactionBoundary(amount, lower)` 400.

`checkAccountBalanceBoundary(user)`:
- balance `> account.boundary.upper` ⇒ `AccountBalanceBoundary(amount=balance, upper, userId)` 400.
- balance `< account.boundary.lower` ⇒ `AccountBalanceBoundary(...lower...)` 400.

Note: the transaction-boundary check applies to the **sender** amount only; the
recipient leg is checked for account boundary only. Preserve this asymmetry.

Controller-level: `comment` `len > 255` ⇒ `ParameterInvalid('comment')` 400
(checked before user lookup). User missing ⇒ `UserNotFound` 404.

### `GET /api/transaction` (list all)
- `limit` (default 25), `offset` (optional).
- `count` = total transaction count (all rows, incl. deleted).
- Page has no explicit ordering in PHP (`findAllPaginated`) — replicate by
  matching default ordering (document and pin via test; prefer id ASC to stay
  deterministic, confirm against demo).
- Response: `{ "count": <int>, "transactions": [...] }`.

### `GET /api/user/{userId}/transaction`
- User missing ⇒ `UserNotFound` 404.
- `count` = count of that user's transactions; page ordered by **id DESC**,
  `limit` (default 25) / `offset`.
- Response: `{ "count": <int>, "transactions": [...] }`.

### `GET /api/user/{userId}/transaction/{transactionId}`
- User missing ⇒ `UserNotFound` 404; transaction missing ⇒
  `TransactionNotFound` 404. (PHP looks up the transaction by id only, not
  scoped to the user — preserve.)
- Response: `{ "transaction": <Transaction> }`.

### `DELETE /api/user/{userId}/transaction/{transactionId}` (revert)
- **`userId` is ignored** by the PHP handler — revert is purely by
  transactionId. Preserve this quirk.
- `revertTransaction(transactionId)` in a locked DB transaction:
  - Load primary tx; missing ⇒ `TransactionNotFound` 404.
  - Include the paired tx (recipient or sender) if present; lock all involved
    transactions and users in ascending id order.
  - If the tx has an article, decrement its `usageCount`.
  - For each tx: if already deleted ⇒ `TransactionNotDeletable` 400; else undo:
    `checkTransactionBoundary(amount)`, `user.balance -= amount`, check account
    boundary, then if `payment.undo.delete` is true **delete the row**, else mark
    `deleted = true`.
  - Return the primary transaction.
- Response: `{ "transaction": <Transaction> }`.

### Deletability (`isDeletable`, used in serializer)
`true` iff: not already deleted **and** `payment.undo.enabled` **and**
(no `payment.undo.timeout` set **or** `created >= now - timeout`).

## Concurrency

PHP uses pessimistic write locks + entity refresh inside a DB transaction, and
acquires locks in sorted id order. Go port:
- Run `doTransaction` / `revertTransaction` inside a GORM transaction.
- Use `SELECT ... FOR UPDATE` (`clause.Locking{Strength: "UPDATE"}`) when loading
  the users/articles/transactions being mutated.
- Acquire locks in ascending id order to preserve the deadlock-avoidance scheme.
- (PHP only forced READ COMMITTED on MySQL; on Postgres the default suffices.)

## Error catalog (code · message)

| Exception (Go id) | HTTP | Message template |
|---|---|---|
| `UserNotFoundException` | 404 | `User '%s' not found` |
| `UserAlreadyExistsException` | 409 | `User '%s' already exists` |
| `ParameterMissingException` | 400 | `Parameter '%s' is missing` |
| `ParameterInvalidException` | 400 | `Parameter '%s' is invalid` |
| `TransactionInvalidException` | 400 | `Transaction invalid` (or custom message) |
| `TransactionBoundaryException` | 400 | `Transaction amount '%d' exceeds upper transaction boundary '%d'` / `... is below lower ...` |
| `AccountBalanceBoundaryException` | 400 | `Transaction amount '%d' exceeds upper account balance boundary '%d' for user '%d'` / `... is below lower ...` |
| `TransactionNotFoundException` | 404 | `Transaction '%d' not found` |
| `TransactionNotDeletableException` | 400 | `Transaction '%d' is not deleteable` (note PHP's spelling) |
| `ArticleNotFoundException` | 404 | `Article '%s' not found` |
| `ArticleInactiveException` | 400 | `Article '%s' (%d) is inactive` |

All emitted via the Epic 1 error envelope `{"error":{class,code,message}}`.
Reproduce messages **verbatim**, including `is not deleteable`.

## Testing

**Unit tests (written and executed):**
- Stale-period parser for PHP relative strings (`"10 day"`, `"5 minute"`, etc.).
- `isActive`, `isDeletable` truth tables across settings + timestamps.
- Name sanitize/validate (control-char strip, length, trim) and email validate.
- `checkTransactionBoundary` / `checkAccountBalanceBoundary` edge cases incl. the
  "0 amount ⇒ invalid" and boundary-disabled (`false`) cases.
- `doTransaction` amount computation for article purchases (qty default,
  amount-null path), and the positive-amount-with-recipient/article guard.
- Natural-order name sort.
- User/Transaction serializer JSON shape + date formatting.

**Integration tests (HTTP, real Postgres):**
- Full user CRUD: create (incl. duplicate 409, invalid name/email 400), get by
  id and by name, list with `active=true/false/absent`, search with limit.
- Deposit / dispense / purchase (with article) / transfer (with recipient),
  asserting both legs, balances, and serialized output.
- Boundary violations (transaction + account, upper + lower) return correct
  error envelopes.
- Revert: success (mark-deleted vs delete-row per `payment.undo.delete`),
  double-revert ⇒ not-deletable, revert of a transfer reverts both legs and
  restores both balances, article usageCount decrement.
- Pagination + ordering for both transaction list endpoints.
- Cross-check representative responses against
  `https://demo.strichliste.org/api/` where reachable.

## Definition of done (Epic 2)

- All ten endpoints implemented, matching PHP status codes, JSON shapes, and
  business rules above.
- `go test ./...` green; integration suite passes against a real Postgres.
- Concurrency: money-moving operations run in locked DB transactions.
- Browser check: create a user, deposit, spend on an article, transfer to
  another user, list and revert transactions — all observable via the API.
- PHP untouched (removal deferred to Epic 5); small, frequent commits.

## Known / accepted divergences (preserved quirks)

- `DELETE /user/{userId}/transaction/{transactionId}` ignores `userId`.
- `GET /user/{userId}/transaction/{transactionId}` does not scope the
  transaction to the user.
- `GET /api/user` never returns disabled users, regardless of `active`.
- `search`/`getUserTransactions` `count` is the page size / per-user count, not
  always a grand total — matched per endpoint as in PHP.
- Misspelling `is not deleteable` retained for byte compatibility.
- Update path does not trim `email` (create does).
