<?php

namespace App\ApiDoc;

use OpenApi\Attributes as OA;

/**
 * OpenAPI component schema for the frozen /api `user` shape.
 *
 * Documentation-only: this class carries no data; it mirrors
 * {@see \App\Serializer\UserSerializer} and is pinned by tests/Controller/Api/*.
 */
#[OA\Schema(
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'email', type: 'string', nullable: true),
        new OA\Property(property: 'balance', type: 'integer', description: 'Balance in cents.'),
        new OA\Property(property: 'isActive', type: 'boolean', description: 'Had a transaction within the staleness window.'),
        new OA\Property(property: 'isDisabled', type: 'boolean'),
        new OA\Property(property: 'created', type: 'string', example: '2026-06-10 12:34:56'),
        new OA\Property(property: 'updated', type: 'string', nullable: true, example: '2026-06-10 12:34:56'),
    ],
)]
final class User
{
}
