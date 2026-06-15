<?php

namespace App\ApiDoc;

use OpenApi\Attributes as OA;

/**
 * OpenAPI component schema for the frozen /api `barcode` shape.
 *
 * Documentation-only: mirrors {@see \App\Serializer\BarcodeSerializer}.
 */
#[OA\Schema(
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'barcode', type: 'string'),
        new OA\Property(property: 'created', type: 'string', example: '2026-06-10 12:34:56'),
    ],
)]
final class Barcode
{
}
