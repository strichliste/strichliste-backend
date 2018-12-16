<?php

namespace App\Exception;

class ParameterMissingException extends ApiException {

    function __construct($parameter) {
        parent::__construct(sprintf("Parameter '%s' is missing", $parameter), 400);
    }
}