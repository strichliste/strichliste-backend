<?php

namespace App\Exception;

class ParameterNotFoundException extends ApiException {

    public function __construct($parameter) {
        parent::__construct(sprintf("Mandarory config value '%s' is missing", $parameter), 500);
    }
}