<?php

namespace App\Dto\Api;

use OpenApi\Attributes as OA;

/**
 * OpenAPI response model for the frozen /api `transaction` shape.
 *
 * Documentation-only: mirrors {@see \App\Serializer\TransactionSerializer}.
 */
final class Transaction
{
    public function __construct(
        #[OA\Property(example: 1)]
        public int $id,
        public User $user,
        public ?int $quantity,
        public ?Article $article,
        #[OA\Property(description: 'Sending user when this transaction received a transfer.')]
        public ?User $sender,
        #[OA\Property(description: 'Receiving user when this transaction sent a transfer.')]
        public ?User $recipient,
        public ?string $comment,
        #[OA\Property(description: 'Signed amount in cents.', example: -150)]
        public int $amount,
        public bool $isDeleted,
        #[OA\Property(description: 'Whether the undo window (payment.undo.*) still allows reverting.')]
        public bool $isDeletable,
        #[OA\Property(example: '2026-06-10 12:34:56')]
        public string $created,
    ) {
    }
}
