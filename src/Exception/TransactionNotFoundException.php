<?php

namespace App\Exception;

use App\Entity\User;

class TransactionNotFoundException extends ApiException {

    function __construct($transactionId) {
        parent::__construct(sprintf("Transaction '%d' not found", $transactionId), 404);
    }
}