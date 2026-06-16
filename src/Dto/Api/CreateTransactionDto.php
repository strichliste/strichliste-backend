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
 *
 * The #[Assert] constraints below are stateless shape checks only. Each is a
 * comparison constraint, so a null (omitted) field is skipped — an omitted
 * quantity still defaults to 1 in the service, and a plain deposit (no
 * articleId/recipientId) is unaffected.
 */
final class CreateTransactionDto
{
    public function __construct(
        #[OA\Property(description: 'Signed amount in cents: positive = deposit, negative = dispense or transfer. Omit when buying an article (the price is derived).', example: -150)]
        public ?int $amount = null,
        // Positive rejects a negative quantity, which would flip the sign of an
        // article purchase into a credit (price * quantity * -1). The upper bound
        // stops price * quantity overflowing PHP's int range into a float, which
        // would otherwise be an unhandled 500 rather than a clean 422.
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(10000)]
        #[OA\Property(description: 'Units to buy together with `articleId`; defaults to 1 when omitted.', example: 1)]
        public ?int $quantity = null,
        #[Assert\Length(max: 255)]
        public ?string $comment = null,
        #[Assert\Positive]
        #[OA\Property(description: 'Recipient user id — makes this a transfer.')]
        public ?int $recipientId = null,
        #[Assert\Positive]
        #[OA\Property(description: 'Article id to purchase.')]
        public ?int $articleId = null,
    ) {
    }
}
