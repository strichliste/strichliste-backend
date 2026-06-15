<?php

namespace App\ApiDoc;

use OpenApi\Attributes as OA;

/**
 * OpenAPI component schema for a tag as embedded in an article.
 *
 * Documentation-only: mirrors {@see \App\Serializer\ArticleTagSerializer}.
 * This schema is never a top-level response payload — it is emitted because
 * {@see Article} references it via a Model in its `tags` array items.
 */
#[OA\Schema(
    type: 'object',
    description: 'Tag as embedded in an article.',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'tag', type: 'string'),
        new OA\Property(property: 'created', type: 'string', example: '2026-06-10 12:34:56'),
    ],
)]
final class ArticleTag
{
}
