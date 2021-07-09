<?php

namespace App\Exception;

use App\Entity\Article;
use App\Entity\Tag;

class ArticleTagAlreadyExistsException extends ApiException {

    function __construct(Article $article, Tag $tag) {
        parent::__construct(sprintf("Article '%s' (%d) already has tag '%s'", $article->getName(),
            $article->getId(), $tag->getTag()), 409);
    }
}