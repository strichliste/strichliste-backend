<?php

namespace App\Dto\Api;

use OpenApi\Attributes as OA;

/**
 * Response payload for the frozen /api `article` shape.
 *
 * Built by {@see \App\Serializer\ArticleSerializer}; doubles as the
 * Nelmio OpenAPI schema.
 */
final class Article
{
    /**
     * @param list<Barcode>    $barcodes
     * @param list<ArticleTag> $tags
     */
    public function __construct(
        #[OA\Property(example: 1)]
        public int $id,
        #[OA\Property(example: 'Club Mate')]
        public string $name,
        public array $barcodes,
        public array $tags,
        #[OA\Property(description: 'Price in cents.', example: 150)]
        public int $amount,
        public bool $isActive,
        #[OA\Property(example: 42)]
        public int $usageCount,
        #[OA\Property(description: 'Previous revision of this article (depth-limited; null at depth 0).')]
        public ?Article $precursor,
        #[OA\Property(example: '2026-06-10 12:34:56')]
        public string $created,
    ) {
    }
}
