<?php

namespace App\Dto\Api;

use OpenApi\Attributes as OA;

/**
 * OpenAPI response model for a single day in the metrics series.
 *
 * Documentation-only: mirrors the per-day rows produced by
 * {@see \App\Service\MetricsService::transactionsPerDay()}.
 */
#[OA\Schema(
    description: 'Daily aggregate. On days WITHOUT transactions, `charged` and `spent` are the integer 0; on days with activity they are {amount, transactions} objects (legacy quirk).',
    required: ['date', 'transactions', 'distinctUsers', 'balance', 'charged', 'spent'],
)]
final class MetricsDay
{
    public function __construct(
        #[OA\Property(example: '2026-06-10')]
        public string $date,
        public int $transactions,
        public int $distinctUsers,
        #[OA\Property(description: 'Net amount of the day in cents.')]
        public int $balance,
        // mixed (int-0 or {amount,transactions}); nullable:false stops the
        // `mixed` PHP type from leaking a spurious `nullable: true`.
        #[OA\Property(nullable: false, oneOf: [
            new OA\Schema(type: 'integer', enum: [0]),
            new OA\Schema(type: 'object', properties: [
                new OA\Property(property: 'amount', type: 'integer'),
                new OA\Property(property: 'transactions', type: 'integer'),
            ]),
        ])]
        public mixed $charged,
        #[OA\Property(nullable: false, oneOf: [
            new OA\Schema(type: 'integer', enum: [0]),
            new OA\Schema(type: 'object', properties: [
                new OA\Property(property: 'amount', type: 'integer'),
                new OA\Property(property: 'transactions', type: 'integer'),
            ]),
        ])]
        public mixed $spent,
    ) {
    }
}
