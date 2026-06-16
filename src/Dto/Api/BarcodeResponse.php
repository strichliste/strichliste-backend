<?php

namespace App\Dto\Api;

/**
 * Envelope for endpoints returning a single barcode: `{ "barcode": { … } }`.
 */
final class BarcodeResponse
{
    public function __construct(
        public Barcode $barcode,
    ) {
    }
}
