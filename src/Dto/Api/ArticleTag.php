<?php

namespace App\Dto\Api;

use OpenApi\Attributes as OA;

/**
 * Response payload for a tag as embedded in an article.
 *
 * Built by {@see \App\Serializer\ArticleTagSerializer}; doubles as the
 * Nelmio OpenAPI schema.
 */
#[OA\Schema(description: 'Tag as embedded in an article.')]
final class ArticleTag
{
    public function __construct(
        #[OA\Property(example: 1)]
        public int $id,
        #[OA\Property(example: 'mate')]
        public string $tag,
        #[OA\Property(example: '2026-06-10 12:34:56')]
        public string $created,
    ) {
    }
}
