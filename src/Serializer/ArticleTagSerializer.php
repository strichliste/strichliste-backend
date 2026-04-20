<?php

namespace App\Serializer;

use App\Entity\ArticleTag;

class ArticleTagSerializer {

    function serialize(ArticleTag $articleTag): array {
        return [
            'id' => $articleTag->getTag()->getId(),
            'tag' => $articleTag->getTag()->getTag(),
            'created' => $articleTag->getCreated()->format('Y-m-d H:i:s')
        ];
    }
}
