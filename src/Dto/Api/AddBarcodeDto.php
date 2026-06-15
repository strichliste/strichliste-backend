<?php

namespace App\Dto\Api;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request payload for POST /api/article/{articleId}/barcode.
 */
#[OA\Schema(required: ['barcode'])]
final class AddBarcodeDto
{
    #[Assert\NotBlank]
    public ?string $barcode;

    public function __construct(?string $barcode = null)
    {
        $this->barcode = null === $barcode ? null : trim($barcode);
    }
}
