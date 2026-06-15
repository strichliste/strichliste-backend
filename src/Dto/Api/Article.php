<?php

namespace App\Dto\Api;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * OpenAPI response model for the frozen /api `article` shape.
 *
 * Documentation-only: mirrors {@see \App\Serializer\ArticleSerializer}.
 */
final class Article
{
    /**
     * @param Barcode[]    $barcodes
     * @param ArticleTag[] $tags
     */
    public function __construct(
        #[OA\Property(example: 1)]
        public int $id,
        #[OA\Property(example: 'Club Mate')]
        public string $name,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: Barcode::class)))]
        public array $barcodes,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: ArticleTag::class)))]
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
