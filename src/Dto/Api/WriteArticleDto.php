<?php

namespace App\Dto\Api;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request payload for POST /api/article and POST /api/article/{articleId}.
 *
 * `amount` is the price in integer cents and must be non-zero (a missing or
 * zero amount was a "parameter missing" error in the legacy contract).
 */
#[OA\Schema(required: ['name', 'amount'])]
final class WriteArticleDto
{
    #[Assert\NotBlank]
    public ?string $name;

    public function __construct(
        ?string $name = null,
        #[Assert\NotBlank]
        #[Assert\NotEqualTo(0)]
        #[OA\Property(description: 'Price in cents.')]
        public ?int $amount = null,
    ) {
        $this->name = null === $name ? null : trim($name);
    }
}
