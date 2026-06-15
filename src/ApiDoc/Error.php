<?php

namespace App\ApiDoc;

use OpenApi\Attributes as OA;

/**
 * OpenAPI component schema for the uniform error envelope shared by all 4xx
 * responses: `{"error": {"class": "...", "code": 4xx, "message": "..."}}`.
 *
 * Documentation-only: mirrors {@see \App\EventSubscriber\ApiExceptionSubscriber}.
 */
#[OA\Schema(
    type: 'object',
    properties: [
        new OA\Property(
            property: 'error',
            type: 'object',
            properties: [
                new OA\Property(property: 'class', type: 'string', description: 'FQCN of the exception — the machine-readable discriminator.', example: 'App\Exception\UserNotFoundException'),
                new OA\Property(property: 'code', type: 'integer', example: 404),
                new OA\Property(property: 'message', type: 'string'),
            ],
        ),
    ],
)]
final class Error
{
}
