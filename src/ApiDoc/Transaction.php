<?php

namespace App\ApiDoc;

use OpenApi\Attributes as OA;

/**
 * OpenAPI component schema for the frozen /api `transaction` shape.
 *
 * Documentation-only: mirrors {@see \App\Serializer\TransactionSerializer}.
 */
#[OA\Schema(
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
        new OA\Property(property: 'quantity', type: 'integer', nullable: true),
        new OA\Property(
            property: 'article',
            nullable: true,
            allOf: [new OA\Schema(ref: '#/components/schemas/Article')],
        ),
        new OA\Property(
            property: 'sender',
            description: 'Sending user when this transaction received a transfer.',
            nullable: true,
            allOf: [new OA\Schema(ref: '#/components/schemas/User')],
        ),
        new OA\Property(
            property: 'recipient',
            description: 'Receiving user when this transaction sent a transfer.',
            nullable: true,
            allOf: [new OA\Schema(ref: '#/components/schemas/User')],
        ),
        new OA\Property(property: 'comment', type: 'string', nullable: true),
        new OA\Property(property: 'amount', type: 'integer', description: 'Signed amount in cents.'),
        new OA\Property(property: 'isDeleted', type: 'boolean'),
        new OA\Property(property: 'isDeletable', type: 'boolean', description: 'Whether the undo window (payment.undo.*) still allows reverting.'),
        new OA\Property(property: 'created', type: 'string', example: '2026-06-10 12:34:56'),
    ],
)]
final class Transaction
{
}
