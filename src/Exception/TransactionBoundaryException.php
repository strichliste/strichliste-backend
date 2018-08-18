<?php

namespace App\Exception;

class TransactionBoundaryException extends ApiException {

    public function __construct($amount, $boundary) {
        if ($amount > $boundary) {
            parent::__construct(sprintf("Transaction amount '%d' exceeds upper transaction boundary '%d'", $amount, $boundary), 400);
        } else {
            parent::__construct(sprintf("Transaction amount '%d' is below lower transaction boundary '%d'", $amount, $boundary), 400);
        }
    }
}