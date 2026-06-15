<?php

namespace App\ApiDoc;

use OpenApi\Attributes as OA;

/**
 * Request body for POST /api/article and POST /api/article/{articleId}.
 * Documentation-only.
 *
 * Mirrors the fields read by {@see \App\Service\ArticleService}; accepted as
 * JSON or form-encoded.
 */
#[OA\Schema(
    type: 'object',
    required: ['name', 'amount'],
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'amount', type: 'integer', description: 'Price in cents.'),
    ],
)]
final class WriteArticleRequest
{
}
