<?php

namespace App\Exception;

class UserNotFoundException extends ApiException {

    public function __construct($identifier) {
        parent::__construct(sprintf("User '%s' not found", $identifier), 404);
    }
}