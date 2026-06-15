<?php

namespace App\ApiDoc;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * OpenAPI component schema for the frozen /api `article` shape.
 *
 * Documentation-only: mirrors {@see \App\Serializer\ArticleSerializer}.
 * `tags` uses a Model reference so the {@see ArticleTag} component is emitted
 * even though it is never returned on its own; `barcodes` and `precursor` use
 * plain $refs to components emitted by their own endpoints / this schema.
 */
#[OA\Schema(
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(
            property: 'barcodes',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Barcode'),
        ),
        new OA\Property(
            property: 'tags',
            type: 'array',
            items: new OA\Items(ref: new Model(type: ArticleTag::class)),
        ),
        new OA\Property(property: 'amount', type: 'integer', description: 'Price in cents.'),
        new OA\Property(property: 'isActive', type: 'boolean'),
        new OA\Property(property: 'usageCount', type: 'integer'),
        new OA\Property(
            property: 'precursor',
            description: 'Previous revision of this article (depth-limited; null at depth 0).',
            nullable: true,
            allOf: [new OA\Schema(ref: '#/components/schemas/Article')],
        ),
        new OA\Property(property: 'created', type: 'string', example: '2026-06-10 12:34:56'),
    ],
)]
final class Article
{
}
