<?php

namespace App\ApiDoc;

use OpenApi\Attributes as OA;

/**
 * OpenAPI component schema for a single day in the metrics series.
 *
 * Documentation-only: mirrors the per-day rows produced by
 * {@see \App\Service\MetricsService::transactionsPerDay()}.
 */
#[OA\Schema(
    type: 'object',
    description: 'Daily aggregate. On days WITHOUT transactions, `charged` and `spent` are the integer 0; on days with activity they are {amount, transactions} objects (legacy quirk).',
    properties: [
        new OA\Property(property: 'date', type: 'string', example: '2026-06-10'),
        new OA\Property(property: 'transactions', type: 'integer'),
        new OA\Property(property: 'distinctUsers', type: 'integer'),
        new OA\Property(property: 'balance', type: 'integer', description: 'Net amount of the day in cents.'),
        new OA\Property(
            property: 'charged',
            oneOf: [
                new OA\Schema(type: 'integer', enum: [0]),
                new OA\Schema(type: 'object', properties: [
                    new OA\Property(property: 'amount', type: 'integer'),
                    new OA\Property(property: 'transactions', type: 'integer'),
                ]),
            ],
        ),
        new OA\Property(
            property: 'spent',
            oneOf: [
                new OA\Schema(type: 'integer', enum: [0]),
                new OA\Schema(type: 'object', properties: [
                    new OA\Property(property: 'amount', type: 'integer'),
                    new OA\Property(property: 'transactions', type: 'integer'),
                ]),
            ],
        ),
    ],
)]
final class MetricsDay
{
}
