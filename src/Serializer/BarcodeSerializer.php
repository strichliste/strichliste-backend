<?php

namespace App\Serializer;

use App\Entity\Barcode;

class BarcodeSerializer {

    function serialize(Barcode $barcode): array {

        return [
            'id' => $barcode->getId(),
            'barcode' => $barcode->getBarcode(),
            'created' => $barcode->getCreated()->format('Y-m-d H:i:s')
        ];
    }
}