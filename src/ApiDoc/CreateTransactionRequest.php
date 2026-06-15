<?php

namespace App\ApiDoc;

use OpenApi\Attributes as OA;

/**
 * Request body for POST /api/user/{userId}/transaction. Documentation-only.
 *
 * A transaction is one of: a deposit/dispense (`amount`), an article purchase
 * (`articleId`, optional `quantity`) or a transfer (`amount` + `recipientId`).
 * Mirrors {@see \App\Controller\Api\TransactionController::createUserTransactions()};
 * accepted as JSON or form-encoded.
 */
#[OA\Schema(
    type: 'object',
    properties: [
        new OA\Property(property: 'amount', type: 'integer', description: 'Signed amount in cents.'),
        new OA\Property(property: 'quantity', type: 'integer'),
        new OA\Property(property: 'comment', type: 'string', maxLength: 255),
        new OA\Property(property: 'recipientId', type: 'integer'),
        new OA\Property(property: 'articleId', type: 'integer'),
    ],
)]
final class CreateTransactionRequest
{
}
