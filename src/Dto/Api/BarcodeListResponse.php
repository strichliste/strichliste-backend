<?php

namespace App\Dto\Api;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * Envelope for barcode lists: `{ "count": N, "barcodes": [ … ] }`.
 */
final class BarcodeListResponse
{
    /**
     * @param Barcode[] $barcodes
     */
    public function __construct(
        public int $count,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: Barcode::class)))]
        public array $barcodes,
    ) {
    }
}
