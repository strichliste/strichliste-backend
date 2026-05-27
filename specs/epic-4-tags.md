# Epic 4 — Tags (Refinement)

Article categorization via tags, using the `article_tag` join (many tags per
article, many articles per tag). Builds on Epic 1 (foundation, models, error
envelope), Epic 3 (Article + ArticleSerializer, the embedded `tags[]` array).

PHP stays as reference until Epic 5.

## Endpoints delivered

| Method | Path | Returns |
|--------|------|---------|
| GET | `/api/tag` | `{count, tags}` (all tags, sorted) |
| GET | `/api/article/{articleId}/tag` | `{count, tags}` (one article's tags) |
| GET | `/api/article/{articleId}/tag/{tagId}` | `{tag}` |
| POST | `/api/article/{articleId}/tag` | `{article}` (note: article, not tag) |
| DELETE | `/api/article/{articleId}/tag/{tagId}` | `{article}` |

## Serialization contracts (exact JSON)

### Tag object (standalone — `TagSerializer`, used by all `/tag` endpoints)
```json
{
  "id": <int>,
  "tag": "<string>",
  "usageCount": <int>,
  "created": "YYYY-MM-DD HH:MM:SS"
}
```
- **`usageCount` is computed** = number of `article_tag` rows referencing this
  tag. Not a stored column.
- `created` is the Tag's own creation timestamp.

### Important: two different tag shapes coexist
- The standalone **Tag** shape above (with `usageCount`, the Tag's own `created`)
  is returned by the `/tag` endpoints in this epic.
- The **embedded ArticleTag** shape inside an Article object (Epic 3) is
  different: `{id: <Tag id>, tag, created: <join-row created>}` — **no
  `usageCount`**, and `created` is the join row's timestamp.
- Same tag, two renderings depending on the endpoint. Preserve both.

Date format: PHP `Y-m-d H:i:s` → Go `2006-01-02 15:04:05`.

## Business logic

### `GET /api/tag` (list all)
- Load all tags.
- **Sort: `usageCount` DESC, then `created` DESC** (tie-break). Replicate the
  PHP spaceship comparator exactly.
- Response `{ "count": <int>, "tags": [ <Tag>... ] }`.

### `GET /api/article/{articleId}/tag`
- Article not found ⇒ `ArticleNotFound(articleId)` 404.
- Tags = the article's tags (the `Tag` behind each of its `article_tag` rows),
  serialized with the standalone Tag serializer (so each carries its global
  `usageCount`, not a per-article count).
- Response `{ "count": <int>, "tags": [...] }` (no explicit sort — match the
  article's tag order; pin via test).

### `GET /api/article/{articleId}/tag/{tagId}`
- Article not found ⇒ `ArticleNotFound` 404.
- Look up the `article_tag` row by `(article=articleId, tag=tagId)`; missing ⇒
  `TagNotFound(tagId)` 404.
- Response `{ "tag": <Tag> }` (the linked Tag, standalone shape).

### `POST /api/article/{articleId}/tag` (add tag)
- `tag` param trimmed; empty ⇒ `ParameterInvalid('tag')` 400.
- Article not found ⇒ `ArticleNotFound` 404.
- Resolve the tag by its string (`findByTag`):
  - If a Tag with that string **exists**:
    - If the article **already has** it ⇒ `ArticleTagAlreadyExists(article, tag)`
      409.
    - Else **reuse** the existing Tag (do not create a duplicate Tag row).
  - If it does **not** exist ⇒ create a new Tag with that string.
- Attach via a new `article_tag` row (`article.addTag(tag)`), persist.
- **Returns the serialized article** (`{article}`), not the tag. The article's
  embedded `tags[]` now includes the new one.

### `DELETE /api/article/{articleId}/tag/{tagId}` (remove tag)
- Article not found ⇒ `ArticleNotFound` 404.
- Look up the `article_tag` row by `(article, tag)`; missing ⇒ `TagNotFound`
  404.
- In a DB transaction: remove the `article_tag` row; **if that Tag's
  `usageCount === 1`** (i.e. this was its only usage), also delete the Tag row
  itself. Then flush.
- Response `{ "article": <Article> }` (the article, now without that tag).

## Error catalog (additions for Epic 4)

| Exception (Go id) | HTTP | Message template |
|---|---|---|
| `ArticleNotFoundException` | 404 | `Article '%s' not found` (reused) |
| `ParameterInvalidException` | 400 | `Parameter '%s' is invalid` (reused) |
| `TagNotFoundException` | 404 | `Tag ID '%d' not found.` (trailing period) |
| `ArticleTagAlreadyExistsException` | 409 | `Article '%s' (%d) already has tag '%s'` |

Emitted via the Epic 1 error envelope; messages verbatim incl. the trailing
period on `TagNotFound`.

## Data / persistence notes

- `usageCount` must be computed from `article_tag` counts. For the `/tag` list
  (needs counts for sorting across all tags), compute counts in one query
  (e.g. `GROUP BY tag_id`) rather than N+1.
- Tag reuse: adding an existing tag string to a new article creates only a new
  `article_tag` row, not a new Tag.
- Orphan cleanup: removing the last `article_tag` for a tag deletes the Tag row.
  Replicate the PHP `usageCount === 1` check at delete time.
- Reuse Epic 2's transactional write pattern for the delete (and the cascade
  cleanup).
- Coordinate with Epic 3 article versioning: when an article is re-versioned,
  its `article_tag` rows are reassigned to the new version (already specified in
  Epic 3) — tag `usageCount` should remain stable across that move.

## Testing

**Unit tests (written and executed):**
- Tag serializer shape incl. computed `usageCount`.
- `/tag` comparator: usageCount-desc with created-desc tie-break (and equal
  cases).
- Add-tag resolution: new tag, reuse existing tag, duplicate-on-article ⇒ 409.
- Delete-tag orphan logic: usageCount==1 deletes the Tag; usageCount>1 keeps it.

**Integration tests (HTTP, real Postgres):**
- List all tags (sorted), list an article's tags, get single article tag
  (mismatch ⇒ `TagNotFound`).
- Add: empty tag 400, new tag creates Tag + link, existing tag reused across two
  articles (one Tag, two links, usageCount=2), duplicate on same article 409;
  success returns the article with updated embedded `tags[]`.
- Delete: removes the link; last usage deletes the Tag, shared tag survives with
  decremented usageCount; success returns the article.
- Verify the two tag renderings (standalone `/tag` vs embedded in the article)
  differ as specified.
- Cross-check representative responses against `https://demo.strichliste.org/api/`
  where reachable.

## Definition of done (Epic 4)

- All five endpoints implemented, matching PHP status codes, JSON shapes, sort
  order, and the rules/quirks above.
- Tag reuse and orphan-cleanup behavior verified.
- `go test ./...` green; integration suite passes against real Postgres.
- Browser check: tag an article, reuse a tag on another article, list tags
  (sorted by usage), untag and observe orphan cleanup.
- PHP untouched (removal deferred to Epic 5); small, frequent commits.

## Known / accepted divergences (preserved quirks)

- Tag `POST` and `DELETE` return the serialized **article**, not the tag.
- Standalone Tag (`/tag` endpoints) and embedded article-tag (Epic 3) use
  different JSON shapes for the same tag.
- A Tag is deleted only when its `usageCount` is exactly 1 at removal time
  (orphan cleanup), mirroring the PHP check.
- `tags[].id` (embedded, Epic 3) is the Tag id; `/tag` endpoints likewise key on
  Tag id.
