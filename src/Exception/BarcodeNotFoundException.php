<?php

namespace App\Exception;

class BarcodeNotFoundException extends ApiException {

    function __construct(int $barcodeId) {
        parent::__construct(sprintf("Barcode ID '%d' not found.", $barcodeId), 404);
    }
}