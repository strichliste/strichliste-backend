<?php

namespace App\ApiDoc;

use OpenApi\Attributes as OA;

/**
 * Request body for POST /api/article/{articleId}/barcode. Documentation-only.
 *
 * Mirrors {@see \App\Controller\Api\BarcodeController::addArticleBarcode()};
 * accepted as JSON or form-encoded.
 */
#[OA\Schema(
    type: 'object',
    required: ['barcode'],
    properties: [
        new OA\Property(property: 'barcode', type: 'string'),
    ],
)]
final class AddBarcodeRequest
{
}
