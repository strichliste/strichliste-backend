<?php

namespace App\Exception;

class TransactionNotFoundException extends ApiException {
    public function __construct($transactionId) {
        parent::__construct(\sprintf("Transaction '%d' not found", $transactionId), 404);
    }
}
