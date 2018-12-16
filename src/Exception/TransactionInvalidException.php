<?php

namespace App\Exception;

class TransactionInvalidException extends ApiException {

    function __construct($message = 'Transaction invalid') {
        parent::__construct($message, 400);
    }
}