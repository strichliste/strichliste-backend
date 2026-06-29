<?php

namespace App\Event;

use App\Entity\Transaction;

/**
 * A transaction was reverted (undone). Dispatched once, after the revert has
 * been committed.
 */
final class TransactionRevertedEvent
{
    public function __construct(public readonly Transaction $transaction)
    {
    }
}
