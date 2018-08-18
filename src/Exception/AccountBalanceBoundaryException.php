<?php

namespace App\Exception;

class AccountBalanceBoundaryException extends ApiException {

    public function __construct($amount, $boundary) {
        if ($amount > $boundary) {
            parent::__construct(sprintf("Transaction amount '%d' exceeds upper account balance boundary '%d'", $amount, $boundary), 400);
        } else {
            parent::__construct(sprintf("Transaction amount '%d' is below lower account balance boundary '%d'", $amount, $boundary), 400);
        }
    }
}