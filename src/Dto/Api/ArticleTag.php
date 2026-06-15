<?php

namespace App\Dto\Api;

use OpenApi\Attributes as OA;

/**
 * OpenAPI response model for a tag as embedded in an article.
 *
 * Documentation-only: mirrors {@see \App\Serializer\ArticleTagSerializer}.
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
