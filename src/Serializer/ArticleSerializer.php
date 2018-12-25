<?php

namespace App\Serializer;

use App\Entity\Article;

class ArticleSerializer {

    function serialize(Article $article): array {
        $precursor = $article->getPrecursor();

        return [
            'id' => $article->getId(),
            'name' => $article->getName(),
            'barcode' => $article->getBarcode(),
            'amount' => $article->getAmount(),
            'isActive' => $article->isActive(),
            'usageCount' => $article->getUsageCount(),
            'precursor' => $precursor ? self::serialize($precursor) : null,
            'created' => $article->getCreated()->format('Y-m-d H:i:s')
        ];
    }

    function serializeWithoutPrecursor(Article $article): array {
        return [
            'id' => $article->getId(),
            'name' => $article->getName(),
            'barcode' => $article->getBarcode(),
            'amount' => $article->getAmount(),
            'isActive' => $article->isActive(),
            'usageCount' => $article->getUsageCount(),
            'precursor' => null,
            'created' => $article->getCreated()->format('Y-m-d H:i:s')
        ];
    }
}