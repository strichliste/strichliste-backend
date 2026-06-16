<?php

namespace App\Serializer;

use App\Dto\Api\ArticleTag as ArticleTagDto;
use App\Entity\ArticleTag;

class ArticleTagSerializer
{
    public function serialize(ArticleTag $articleTag): ArticleTagDto
    {
        return new ArticleTagDto(
            id: $articleTag->getTag()->getId(),
            tag: $articleTag->getTag()->getTag(),
            created: $articleTag->getCreated()->format('Y-m-d H:i:s'),
        );
    }
}
