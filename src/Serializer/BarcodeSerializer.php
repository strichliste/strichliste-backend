<?php

namespace App\Serializer;

use App\Entity\Barcode;

class BarcodeSerializer
{
    public function serialize(Barcode $barcode): array
    {
        return [
            'id' => $barcode->getId(),
            'barcode' => $barcode->getBarcode(),
            'created' => $barcode->getCreated()->format('Y-m-d H:i:s'),
        ];
    }
}
