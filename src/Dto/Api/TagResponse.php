<?php

namespace App\Dto\Api;

/**
 * Envelope for endpoints returning a single tag: `{ "tag": { … } }`.
 */
final class TagResponse
{
    public function __construct(
        public Tag $tag,
    ) {
    }
}
