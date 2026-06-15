<?php

namespace App\ApiDoc;

use OpenApi\Attributes as OA;

/**
 * Request body for POST /api/user. Documentation-only.
 *
 * Mirrors the fields read by {@see \App\Controller\Api\UserController::createUser()};
 * accepted as JSON or form-encoded.
 */
#[OA\Schema(
    type: 'object',
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 64),
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255),
    ],
)]
final class CreateUserRequest
{
}
