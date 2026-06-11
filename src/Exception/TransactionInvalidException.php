<?php

namespace App\Exception;

class TransactionInvalidException extends ApiException
{
    public function __construct(string $message = 'Transaction invalid')
    {
        parent::__construct($message, 400);
    }
}
