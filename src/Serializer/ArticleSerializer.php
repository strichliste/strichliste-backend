<?php

namespace App\Serializer;

use App\Entity\Article;
use App\Entity\Barcode;
use App\Entity\Tag;

class ArticleSerializer {

    private $barcodeSerializer;

    private $tagSerializer;

    function __construct(BarcodeSerializer $barcodeSerializer, TagSerializer $tagSerializer) {
        $this->barcodeSerializer = $barcodeSerializer;
        $this->tagSerializer = $tagSerializer;
    }

    function serialize(Article $article, $depth = 1): array {

        $precursor = null;
        if ($depth > 0) {
            $precursor = $article->getPrecursor();
        }

        $barcodes = $article->getBarcodes()->getValues();
        $tags = $article->getTags()->getValues();

        return [
            'id' => $article->getId(),
            'name' => $article->getName(),
            'barcodes' => array_map(function(Barcode $barcode) {
                return $this->barcodeSerializer->serialize($barcode);
            }, $barcodes),
            'tags' => array_map(function(Tag $tag) {
                return $this->tagSerializer->serialize($tag);
            }, $tags),
            'amount' => $article->getAmount(),
            'isActive' => $article->isActive(),
            'usageCount' => $article->getUsageCount(),
            'precursor' => $precursor ? self::serialize($precursor, $depth - 1) : null,
            'created' => $article->getCreated()->format('Y-m-d H:i:s')
        ];
    }
}