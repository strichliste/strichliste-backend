<?php

namespace App\Exception;

class ParameterInvalidException extends ApiException {
    public function __construct($parameter) {
        parent::__construct(\sprintf("Parameter '%s' is invalid", $parameter), 400);
    }
}
