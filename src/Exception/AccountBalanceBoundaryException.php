<?php

namespace App\Exception;

use App\Entity\User;

class AccountBalanceBoundaryException extends ApiException {

    public function __construct(User $user, $amount, $boundary) {
        if ($amount > $boundary) {
            parent::__construct(sprintf("Transaction amount '%d' exceeds upper account balance boundary '%d' for user '%d'", $amount, $boundary, $user->getId()), 400);
        } else {
            parent::__construct(sprintf("Transaction amount '%d' is below lower account balance boundary '%d' for user '%d'", $amount, $boundary, $user->getId()), 400);
        }
    }
}