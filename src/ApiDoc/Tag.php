<?php

namespace App\ApiDoc;

use OpenApi\Attributes as OA;

/**
 * OpenAPI component schema for the frozen /api `tag` shape.
 *
 * Documentation-only: mirrors {@see \App\Serializer\TagSerializer}.
 */
#[OA\Schema(
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'tag', type: 'string'),
        new OA\Property(property: 'usageCount', type: 'integer'),
        new OA\Property(property: 'created', type: 'string', example: '2026-06-10 12:34:56'),
    ],
)]
final class Tag
{
}
