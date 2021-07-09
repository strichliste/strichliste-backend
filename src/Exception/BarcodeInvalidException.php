<?php

namespace App\Exception;

class BarcodeInvalidException extends ApiException {

    function __construct(string $barcode) {
        parent::__construct(sprintf("Barcode '%s' is invalid.", $barcode), 400);
    }
}