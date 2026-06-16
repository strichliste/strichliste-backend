<?php

namespace App\Dto\Api;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * Envelope for transaction lists: `{ "count": N, "transactions": [ … ] }`.
 */
final class TransactionListResponse
{
    /**
     * @param Transaction[] $transactions
     */
    public function __construct(
        public int $count,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: Transaction::class)))]
        public array $transactions,
    ) {
    }
}
