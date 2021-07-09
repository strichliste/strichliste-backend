<?php

namespace App\Exception;

class TagNotFoundException extends ApiException {

    function __construct(int $tagId) {
        parent::__construct(sprintf("Tag ID '%d' not found.", $tagId), 404);
    }
}