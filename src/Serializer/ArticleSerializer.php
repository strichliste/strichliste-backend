<?php

namespace App\Serializer;

use App\Entity\Article;

class ArticleSerializer {
    public function serialize(Article $article, $depth = 1): array {
        $precursor = null;
        if ($depth > 0) {
            $precursor = $article->getPrecursor();
        }

        return [
            'id' => $article->getId(),
            'name' => $article->getName(),
            'barcode' => $article->getBarcode(),
            'amount' => $article->getAmount(),
            'isActive' => $article->isActive(),
            'usageCount' => $article->getUsageCount(),
            'precursor' => $precursor instanceof Article ? self::serialize($precursor, $depth - 1) : null,
            'created' => $article->getCreated()->format('Y-m-d H:i:s'),
        ];
    }
}
