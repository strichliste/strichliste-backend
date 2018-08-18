<?php

namespace App\Exception;

use App\Entity\User;

class TransactionNotFoundException extends ApiException {

    public function __construct(User $user, $transactionId) {
        parent::__construct(sprintf("Transaction '%d' not found for user '%d'", $transactionId, $user->getId()), 404);
    }
}