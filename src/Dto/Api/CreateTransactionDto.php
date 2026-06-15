<?php

namespace App\Dto\Api;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request payload for POST /api/user/{userId}/transaction.
 *
 * One of: a deposit/dispense (`amount`), an article purchase (`articleId`,
 * optional `quantity`) or a transfer (`amount` + `recipientId`). Amounts are
 * signed integer cents. The amount-sign / boundary / mutual-exclusivity rules
 * live in TransactionService and are intentionally not duplicated here.
 */
final class CreateTransactionDto
{
    public function __construct(
        #[OA\Property(description: 'Signed amount in cents.')]
        public ?int $amount = null,
        public ?int $quantity = null,
        #[Assert\Length(max: 255)]
        public ?string $comment = null,
        public ?int $recipientId = null,
        public ?int $articleId = null,
    ) {
    }
}
