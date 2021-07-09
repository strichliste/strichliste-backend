<?php

namespace App\Exception;

use App\Entity\Barcode;

class ArticleBarcodeAlreadyExistsException extends ApiException {

    function __construct(Barcode $barcode) {
        $article = $barcode->getArticle();

        parent::__construct(sprintf("Active article '%s' (%d) with barcode '%s' already exists.", $article->getName(),
            $article->getId(), $barcode->getBarcode()), 409);
    }
}