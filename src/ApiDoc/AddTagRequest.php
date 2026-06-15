<?php

namespace App\ApiDoc;

use OpenApi\Attributes as OA;

/**
 * Request body for POST /api/article/{articleId}/tag. Documentation-only.
 *
 * Mirrors {@see \App\Controller\Api\TagController::addArticleTag()};
 * accepted as JSON or form-encoded.
 */
#[OA\Schema(
    type: 'object',
    required: ['tag'],
    properties: [
        new OA\Property(property: 'tag', type: 'string'),
    ],
)]
final class AddTagRequest
{
}
