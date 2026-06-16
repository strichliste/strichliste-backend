<?php

namespace App\Dto\Api;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * Envelope for tag lists: `{ "count": N, "tags": [ … ] }`.
 */
final class TagListResponse
{
    /**
     * @param Tag[] $tags
     */
    public function __construct(
        public int $count,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: Tag::class)))]
        public array $tags,
    ) {
    }
}
