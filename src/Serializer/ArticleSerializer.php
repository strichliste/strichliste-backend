<?php

namespace App\Serializer;

use App\Entity\Article;
use App\Entity\ArticleTag;
use App\Entity\Barcode;

class ArticleSerializer
{
    public function __construct(private readonly BarcodeSerializer $barcodeSerializer, private readonly ArticleTagSerializer $articleTagSerializer)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Article $article, int $depth = 1): array
    {
        $precursor = null;
        if ($depth > 0) {
            $precursor = $article->getPrecursor();
        }

        return [
            'id' => $article->getId(),
            'name' => $article->getName(),
            'barcodes' => array_map(fn (Barcode $barcode) => $this->barcodeSerializer->serialize($barcode), $article->getBarcodes()),
            'tags' => array_map(fn (ArticleTag $articleTag) => $this->articleTagSerializer->serialize($articleTag), $article->getArticleTags()),
            'amount' => $article->getAmount(),
            'isActive' => $article->isActive(),
            'usageCount' => $article->getUsageCount(),
            'precursor' => $precursor ? self::serialize($precursor, $depth - 1) : null,
            'created' => $article->getCreated()->format('Y-m-d H:i:s'),
        ];
    }
}
