<?php

namespace App\Dto\Api;

use OpenApi\Attributes as OA;

/**
 * OpenAPI response model for the frozen /api `barcode` shape.
 *
 * Documentation-only: mirrors {@see \App\Serializer\BarcodeSerializer}.
 */
final class Barcode
{
    public function __construct(
        #[OA\Property(example: 1)]
        public int $id,
        #[OA\Property(example: '4001234567890')]
        public string $barcode,
        #[OA\Property(example: '2026-06-10 12:34:56')]
        public string $created,
    ) {
    }
}
