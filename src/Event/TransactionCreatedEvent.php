<?php

namespace App\Event;

use App\Entity\Transaction;

/**
 * A transaction was created (deposit, dispense, transfer, purchase or a single
 * row of a split). Dispatched once per transaction, after the surrounding
 * database transaction has committed.
 */
final class TransactionCreatedEvent
{
    public function __construct(public readonly Transaction $transaction)
    {
    }
}
