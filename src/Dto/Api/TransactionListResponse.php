<?php

namespace App\Dto\Api;

/**
 * Envelope for transaction lists: `{ "count": N, "transactions": [ … ] }`.
 */
final class TransactionListResponse
{
    /**
     * @param list<Transaction> $transactions
     */
    public function __construct(
        public int $count,
        public array $transactions,
    ) {
    }
}
