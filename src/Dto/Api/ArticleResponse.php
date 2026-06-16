<?php

namespace App\Dto\Api;

/**
 * Envelope for endpoints returning a single article: `{ "article": { … } }`.
 */
final class ArticleResponse
{
    public function __construct(
        public Article $article,
    ) {
    }
}
