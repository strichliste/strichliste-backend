<?php

namespace App\Serializer;

use App\Entity\Article;
use App\Entity\ArticleTag;
use App\Entity\Barcode;

class ArticleSerializer {

    private $barcodeSerializer;

    private $articleTagSerializer;

    function __construct(BarcodeSerializer $barcodeSerializer, ArticleTagSerializer $articleTagSerializer) {
        $this->barcodeSerializer = $barcodeSerializer;
        $this->articleTagSerializer = $articleTagSerializer;
    }

    function serialize(Article $article, $depth = 1): array {

        $precursor = null;
        if ($depth > 0) {
            $precursor = $article->getPrecursor();
        }

        return [
            'id' => $article->getId(),
            'name' => $article->getName(),
            'barcodes' => array_map(function(Barcode $barcode) {
                return $this->barcodeSerializer->serialize($barcode);
            }, $article->getBarcodes()),
            'tags' => array_map(function(ArticleTag $articleTag) {
                return $this->articleTagSerializer->serialize($articleTag);
            }, $article->getArticleTags()),
            'amount' => $article->getAmount(),
            'isActive' => $article->isActive(),
            'isActivatable' => $article->isActivatable(),
            'usageCount' => $article->getUsageCount(),
            'precursor' => $precursor ? self::serialize($precursor, $depth - 1) : null,
            'created' => $article->getCreated()->format('Y-m-d H:i:s')
        ];
    }

}