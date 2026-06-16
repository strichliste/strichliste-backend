<?php

namespace App\Dto\Api;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * Envelope for article lists: `{ "count": N, "articles": [ … ] }`.
 */
final class ArticleListResponse
{
    /**
     * @param Article[] $articles
     */
    public function __construct(
        public int $count,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: Article::class)))]
        public array $articles,
    ) {
    }
}
