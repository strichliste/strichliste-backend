<?php

namespace App\Dto\Api;

/**
 * Envelope for endpoints returning a single transaction: `{ "transaction": { … } }`.
 */
final class TransactionResponse
{
    public function __construct(
        public Transaction $transaction,
    ) {
    }
}
