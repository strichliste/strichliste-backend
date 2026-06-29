<?php

namespace App\Event;

use App\Entity\Article;

/**
 * An article was created. Dispatched once, after the create has been committed.
 */
final class ArticleCreatedEvent
{
    public function __construct(public readonly Article $article)
    {
    }
}
