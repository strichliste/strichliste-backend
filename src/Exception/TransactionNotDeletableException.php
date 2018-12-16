<?php

namespace App\Exception;

use App\Entity\Transaction;

class TransactionNotDeletableException extends ApiException {

    function __construct(Transaction $transaction) {
        parent::__construct(sprintf("Transaction '%d' is not deleteable", $transaction->getId()), 400);
    }
}