<?php

namespace App\Exception;

class ArticleNotFoundException extends ApiException {

    function __construct($articleId) {
        parent::__construct(sprintf("Article '%s' not found", $articleId), 404);
    }
}