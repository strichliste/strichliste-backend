<?php

namespace App\Dto\Api;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request payload for POST /api/article/{articleId}/tag.
 */
final class AddTagDto
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $tag;

    public function __construct(string $tag)
    {
        $this->tag = trim($tag);
    }
}
