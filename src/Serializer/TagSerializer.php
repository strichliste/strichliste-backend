<?php

namespace App\Serializer;

use App\Entity\Tag;

class TagSerializer {

    function serialize(Tag $tag): array {

        return [
            'id' => $tag->getId(),
            'barcode' => $tag->getTag(),
            'created' => $tag->getCreated()->format('Y-m-d H:i:s')
        ];
    }
}