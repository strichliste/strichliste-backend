<?php

namespace App\Dto\Api;

/**
 * Envelope for article lists: `{ "count": N, "articles": [ … ] }`.
 */
final class ArticleListResponse
{
    /**
     * @param list<Article> $articles
     */
    public function __construct(
        public int $count,
        public array $articles,
    ) {
    }
}
