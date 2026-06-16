<?php

namespace App\Serializer;

use App\Dto\Api\Tag as TagDto;
use App\Entity\Tag;

class TagSerializer
{
    public function serialize(Tag $tag): TagDto
    {
        return new TagDto(
            id: $tag->getId(),
            tag: $tag->getTag(),
            usageCount: $tag->getUsageCount(),
            created: $tag->getCreated()->format('Y-m-d H:i:s'),
        );
    }
}
