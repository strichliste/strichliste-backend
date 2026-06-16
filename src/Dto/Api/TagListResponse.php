<?php

namespace App\Dto\Api;

/**
 * Envelope for tag lists: `{ "count": N, "tags": [ … ] }`.
 */
final class TagListResponse
{
    /**
     * @param list<Tag> $tags
     */
    public function __construct(
        public int $count,
        public array $tags,
    ) {
    }
}
