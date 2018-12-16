<?php

namespace App\Exception;

use App\Entity\Article;

class ArticleInactiveException extends ApiException {

    function __construct(Article $article) {
        parent::__construct(sprintf("Article '%s' (%d) is inactive", $article->getName(), $article->getId()), 400);
    }
}