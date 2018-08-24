<?php

namespace App\Exception;

class TransactionInvalidException extends ApiException {

    public function __construct() {
        parent::__construct("Transaction invalid", 400);
    }
}