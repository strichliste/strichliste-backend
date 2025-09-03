<?php

namespace App\Exception;

use App\Entity\Article;

class ArticleBarcodeAlreadyExistsException extends ApiException {
    public function __construct(Article $article) {
        parent::__construct(\sprintf("Active article (%d) with barcode '%s' already exists.", $article->getId(), $article->getBarcode()), 409);
    }
}
