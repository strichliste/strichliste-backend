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
    #[Assert\Length(max: 255)]
    public string $name;

    public function __construct(
        string $name = '',
        #[Assert\NotEqualTo(0)]
        #[OA\Property(description: 'Price in cents. Must be non-zero — a missing or 0 amount is rejected (422).')]
        public int $amount = 0,
    ) {
        $this->name = trim($name);
    }
}
