<?php

namespace App\Serializer;

use App\Entity\ArticleTag;

class ArticleTagSerializer {

    private $tagSerializer;

    function __construct(TagSerializer $tagSerializer) {
        $this->tagSerializer = $tagSerializer;
    }

    function serialize(ArticleTag $articleTag): array {
        return [
            'id' => $articleTag->getId(),
            'tag' => $articleTag->getTag()->getTag(),
            'created' => $articleTag->getCreated()->format('Y-m-d H:i:s')
        ];
    }
}