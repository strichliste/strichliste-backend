<?php

namespace App\Serializer;

use App\Dto\Api\Article as ArticleDto;
use App\Entity\Article;

class ArticleSerializer
{
    public function __construct(private readonly BarcodeSerializer $barcodeSerializer, private readonly ArticleTagSerializer $articleTagSerializer)
    {
    }

    public function serialize(Article $article, int $depth = 1): ArticleDto
    {
        $precursor = null;
        if ($depth > 0) {
            $precursor = $article->getPrecursor();
        }

        return new ArticleDto(
            id: (int) $article->getId(),
            name: (string) $article->getName(),
            barcodes: array_map($this->barcodeSerializer->serialize(...), $article->getBarcodes()),
            tags: array_map($this->articleTagSerializer->serialize(...), $article->getArticleTags()),
            amount: $article->getAmount(),
            isActive: (bool) $article->isActive(),
            usageCount: (int) $article->getUsageCount(),
            precursor: $precursor ? $this->serialize($precursor, $depth - 1) : null,
            created: $article->getCreated()->format('Y-m-d H:i:s'),
        );
    }
}
