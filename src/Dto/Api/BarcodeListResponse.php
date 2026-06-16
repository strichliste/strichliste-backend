<?php

namespace App\Dto\Api;

/**
 * Envelope for barcode lists: `{ "count": N, "barcodes": [ … ] }`.
 */
final class BarcodeListResponse
{
    /**
     * @param list<Barcode> $barcodes
     */
    public function __construct(
        public int $count,
        public array $barcodes,
    ) {
    }
}
