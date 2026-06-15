<?php

namespace App\Dto\Api;

use OpenApi\Attributes as OA;

/**
 * OpenAPI response model for the frozen /api `tag` shape.
 *
 * Documentation-only: mirrors {@see \App\Serializer\TagSerializer}.
 */
final class Tag
{
    public function __construct(
        #[OA\Property(example: 1)]
        public int $id,
        #[OA\Property(example: 'mate')]
        public string $tag,
        #[OA\Property(example: 3)]
        public int $usageCount,
        #[OA\Property(example: '2026-06-10 12:34:56')]
        public string $created,
    ) {
    }
}
