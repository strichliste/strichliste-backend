<?php

namespace App\Exception;

class UserAlreadyExistsException extends ApiException
{
    public function __construct(int|string $identifier)
    {
        parent::__construct(sprintf("User '%s' already exists", $identifier), 409);
    }
}
