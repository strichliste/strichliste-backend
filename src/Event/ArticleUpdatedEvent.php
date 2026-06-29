<?php

namespace App\Event;

use App\Entity\Article;

/**
 * An article was updated. Carries the resulting active article — which is a new
 * revision when the old one was already referenced by transactions. Dispatched
 * once, after the update has been committed.
 */
final class ArticleUpdatedEvent
{
    public function __construct(public readonly Article $article)
    {
    }
}
