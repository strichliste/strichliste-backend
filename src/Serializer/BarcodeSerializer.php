<?php

namespace App\Serializer;

use App\Dto\Api\Barcode as BarcodeDto;
use App\Entity\Barcode;

class BarcodeSerializer
{
    public function serialize(Barcode $barcode): BarcodeDto
    {
        return new BarcodeDto(
            id: (int) $barcode->getId(),
            barcode: $barcode->getBarcode(),
            created: $barcode->getCreated()->format('Y-m-d H:i:s'),
        );
    }
}
