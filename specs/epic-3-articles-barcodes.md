# Epic 3 — Articles & Barcodes (Refinement)

Purchasable items and their barcode lookups, including the article **versioning**
(precursor chain) that protects historical transactions. Builds on Epic 1
(foundation, models, error envelope) and Epic 2 (transactions, `usageCount`
already incremented/decremented by purchases/reverts).

All amounts are in **cents** (integers). PHP stays as reference until Epic 5.

## Endpoints delivered

| Method | Path | Returns |
|--------|------|---------|
| GET | `/api/article` | `{count, articles}` (active count + filtered page) |
| POST | `/api/article` | `{article}` |
| GET | `/api/article/search` | `{count, articles}` |
| GET | `/api/article/{articleId}` | `{article}` (depth param) |
| POST | `/api/article/{articleId}` | `{article}` (update / versioning) |
| DELETE | `/api/article/{articleId}` | `{article}` (soft delete) |
| GET | `/api/barcode` | `{count, barcodes}` |
| GET | `/api/article/{articleId}/barcode` | `{count, barcodes}` |
| GET | `/api/article/{articleId}/barcode/{barcodeId}` | `{barcode}` |
| POST | `/api/article/{articleId}/barcode` | `{article}` (note: article, not barcode) |
| DELETE | `/api/article/{articleId}/barcode/{barcodeId}` | `{article}` |

## Serialization contracts (exact JSON)

### Article object
```json
{
  "id": <int>,
  "name": "<string>",
  "barcodes": [ <Barcode object>... ],
  "tags": [ <embedded ArticleTag>... ],
  "amount": <int cents>,
  "isActive": <bool>,
  "usageCount": <int>,
  "precursor": <Article object|null>,
  "created": "YYYY-MM-DD HH:MM:SS"
}
```
- `precursor` recursion is **depth-controlled**: serialize takes a `depth`
  (default 1). `precursor` is included only while `depth > 0`, and the precursor
  itself is serialized with `depth - 1`. So default responses include exactly one
  level of precursor; its own `precursor` is null.
- Article has **no `updated` field** (only `created`); `created` set on persist.

### Barcode object
```json
{ "id": <int>, "barcode": "<string>", "created": "YYYY-MM-DD HH:MM:SS" }
```

### Embedded ArticleTag (the `tags` array inside an Article)
```json
{ "id": <tag id>, "tag": "<tag string>", "created": "<ArticleTag.created>" }
```
- **`id` is the Tag's id**, not the ArticleTag join-row id; `created` is the
  join row's timestamp. This read-only embedded shape ships in Epic 3; full tag
  management endpoints come in Epic 4. (The standalone Tag serializer with
  `usageCount` is an Epic 4 concern.)

Date format everywhere: PHP `Y-m-d H:i:s` → Go `2006-01-02 15:04:05`.

## Business logic

### `GET /api/article` (list)
Query params:
- `limit` (default 25), `offset` (optional).
- `active` boolean (default **true**) — Symfony `getBoolean` truthiness
  (`1/true/on/yes`); filters `a1.active = :active`.
- `barcode` (trimmed) — if non-empty, join barcodes and filter
  `b.barcode = :barcode` (exact).
- `precursor` boolean (default true) — if **false**, add `a1.precursor IS NULL`
  (exclude versioned successors).
- `ancestor` = `"true"` / `"false"` — left-join other articles whose
  `precursor = a1.id`; `"true"` keeps only articles that ARE a precursor of
  another (`a2.id IS NOT NULL`), `"false"` keeps only those that are not.
- Group by article, order by `name ASC`, paginate.
- **`count` is `countActive()` — the total number of active articles, ignoring
  all filters and pagination.** Preserve this quirk.

### `POST /api/article` (create — `createArticleByRequest`)
- `name` required (falsy ⇒ `ParameterMissing('name')` 400); stored **trimmed**.
- `amount` = `(int)` of request value (default 0); falsy/0 ⇒
  `ParameterMissing('amount')` 400. (Negative amounts are allowed — truthy.)
- New article: `active=true`, `usageCount=0`. Response `{article}`.

### `GET /api/article/search`
Params `query`, `limit` (25), `barcode` (trimmed), `tag` (trimmed). Join
barcodes, article_tags, tags. Filter logic (preserve exactly, including the
`->where` replacement semantics):
- If `barcode` set: filter `b.barcode = :barcode` (and `query` is disabled).
- If `tag` set: filter `t.tag = :tag` — this **replaces** the barcode filter if
  both are given (Doctrine `->where` overwrites). Preserve: `tag` wins over
  `barcode`.
- Else (`query` still truthy): `b.barcode = query OR t.tag = query OR
  a.name LIKE %query%`.
- Always `AND a.active = true`, order by name, `groupBy` article, cap at limit.
- `count` = number of returned rows. Response `{count, articles}`.

### `GET /api/article/{articleId}`
- `depth` param (default 1) passed to the serializer (controls precursor chain).
- Not found ⇒ `ArticleNotFound(articleId)` 404. Response `{article}`.

### `POST /api/article/{articleId}` (update — `ArticleService::updateArticle`)
- Not found ⇒ `ArticleNotFound` 404; `!active` ⇒ `ArticleInactive` 400.
- Build a candidate from the request (same name/amount validation as create).
- **Reference count = number of transactions referencing this article.**
  - If **0** (never purchased): update `name` + `amount` in place, return the
    same article (same id).
  - If **> 0** (has history): create a **new** article version —
    `newArticle.precursor = oldArticle`, `newArticle.usageCount =
    oldArticle.usageCount`; **reassign all barcodes and article-tags** from old
    to new; set `oldArticle.active = false`; persist both in a DB transaction;
    return the **new** article (new id). This preserves the historical article
    referenced by past transactions.
- Response `{article}`.

### `DELETE /api/article/{articleId}` (soft delete)
- Not found ⇒ `ArticleNotFound` 404.
- Remove **all** of the article's barcodes, set `active = false`, flush.
- Returns the (now inactive) article: `{article}`. No row deletion.

### Barcode endpoints
- **`GET /api/barcode`**: all barcodes ordered `created DESC`. `{count, barcodes}`.
- **`GET /api/article/{articleId}/barcode`**: article not found ⇒ `ArticleNotFound`
  404; else list the article's barcodes. `{count, barcodes}`.
- **`GET /api/article/{articleId}/barcode/{barcodeId}`**: article not found ⇒
  `ArticleNotFound` 404; barcode missing **or** its article id ≠ `articleId` ⇒
  `BarcodeNotFound(barcodeId)` 404. `{barcode}`.
- **`POST /api/article/{articleId}/barcode`**: `barcode` trimmed, empty ⇒
  `ParameterInvalid('barcode')` 400. Article not found ⇒ `ArticleNotFound` 404;
  `!active` ⇒ `ArticleInactive` 400. If the barcode string already exists
  anywhere ⇒ `ArticleBarcodeAlreadyExists(existing)` 409. Else add to article,
  persist. **Returns the serialized article** (`{article}`), not the barcode.
- **`DELETE /api/article/{articleId}/barcode/{barcodeId}`**: article not found ⇒
  `ArticleNotFound` 404; barcode missing or article mismatch ⇒ `BarcodeNotFound`
  404. Remove the barcode row. **Returns `{article}`.**

## Error catalog (additions for Epic 3)

| Exception (Go id) | HTTP | Message template |
|---|---|---|
| `ArticleNotFoundException` | 404 | `Article '%s' not found` |
| `ArticleInactiveException` | 400 | `Article '%s' (%d) is inactive` |
| `ParameterMissingException` | 400 | `Parameter '%s' is missing` |
| `ParameterInvalidException` | 400 | `Parameter '%s' is invalid` |
| `BarcodeNotFoundException` | 404 | `Barcode ID '%d' not found.` (trailing period) |
| `ArticleBarcodeAlreadyExistsException` | 409 | `Active article '%s' (%d) with barcode '%s' already exists.` (trailing period) |

Emitted via the Epic 1 error envelope; reproduce messages verbatim incl. trailing
periods.

## Data / persistence notes

- Eager-load `barcodes` and `articleTags` (with their `tag`) when serializing
  articles, matching Doctrine's EAGER fetch, to avoid N+1 and to fill the
  embedded arrays.
- Versioning must keep the precursor row intact (referenced by historical
  transactions from Epic 2); only `active` flips to false.
- `usageCount` is carried forward to the new version on update.
- Reuse the locked-transaction pattern from Epic 2 for the versioning write.

## Testing

**Unit tests (written and executed):**
- `createArticleByRequest`: name required/trimmed, amount `(int)` cast, 0-amount
  rejection, negative-amount acceptance.
- Article serializer: barcodes/tags arrays, embedded ArticleTag shape (Tag id +
  join created), precursor depth recursion (depth 0/1/2).
- Barcode serializer shape.
- `updateArticle` branching: reference-count 0 (in-place) vs > 0 (new version,
  precursor set, barcodes/tags reassigned, old deactivated, usageCount carried).
- List filter builder: active/barcode/precursor/ancestor combinations; `count`
  stays `countActive()`.
- Search filter precedence: barcode-only, tag-only, tag-overrides-barcode,
  free-text OR.

**Integration tests (HTTP, real Postgres):**
- Article CRUD: create (missing name/amount 400), get (depth), list (filters +
  pagination + active count quirk), search (all precedence paths), soft-delete
  (active=false, barcodes removed), update both branches incl. verifying a past
  transaction still points at the precursor.
- Barcode: list all (created DESC), list per article, get single (mismatch ⇒
  404), add (empty/duplicate/inactive errors; success returns article),
  delete (mismatch ⇒ 404; success returns article).
- Purchase integration with Epic 2: buy an article, confirm `usageCount`
  increments and the transaction serializes the embedded article correctly;
  revert decrements.
- Cross-check representative responses against `https://demo.strichliste.org/api/`
  where reachable.

## Definition of done (Epic 3)

- All eleven endpoints implemented, matching PHP status codes, JSON shapes, and
  the rules/quirks above.
- Article versioning preserves historical transaction references.
- `go test ./...` green; integration suite passes against real Postgres.
- Browser check: create an article, add/lookup/delete a barcode, search,
  purchase it (Epic 2), update a used article (new version appears), soft-delete.
- PHP untouched (removal deferred to Epic 5); small, frequent commits.

## Known / accepted divergences (preserved quirks)

- `GET /api/article` `count` reflects total active articles, ignoring filters
  and pagination.
- `search` `tag` filter overrides `barcode` when both are supplied.
- Barcode `POST` and `DELETE` return the serialized **article**, not the barcode.
- Embedded article `tags[].id` is the Tag id (not the join-row id).
- Trailing periods in `BarcodeNotFound` / `ArticleBarcodeAlreadyExists` messages
  retained.
