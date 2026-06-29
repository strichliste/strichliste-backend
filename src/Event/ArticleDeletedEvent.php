<?php

namespace App\Event;

use App\Entity\Article;

/**
 * An article was deactivated (soft-deleted). Dispatched once, after the change
 * has been committed.
 */
final class ArticleDeletedEvent
{
    public function __construct(public readonly Article $article)
    {
    }
}
