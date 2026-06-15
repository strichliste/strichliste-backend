<?php

namespace App\Dto\Api;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request payload for POST /api/article/{articleId}/tag.
 */
final class AddTagDto
{
    #[Assert\NotBlank]
    public ?string $tag;

    public function __construct(?string $tag = null)
    {
        $this->tag = null === $tag ? null : trim($tag);
    }
}
