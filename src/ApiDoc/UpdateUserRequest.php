<?php

namespace App\ApiDoc;

use OpenApi\Attributes as OA;

/**
 * Request body for POST /api/user/{userId}. Documentation-only.
 *
 * Every field is optional — the action updates only the fields present.
 * Mirrors {@see \App\Controller\Api\UserController::updateUser()};
 * accepted as JSON or form-encoded.
 */
#[OA\Schema(
    type: 'object',
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 64),
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255),
        new OA\Property(property: 'isDisabled', type: 'boolean'),
    ],
)]
final class UpdateUserRequest
{
}
