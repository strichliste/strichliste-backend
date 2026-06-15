<?php

namespace App\Exception;

class UserNotFoundException extends ApiException
{
    public function __construct(int|string $identifier)
    {
        parent::__construct(sprintf("User '%s' not found", $identifier), 404);
    }
}
