<?php

namespace App\Dto\Api;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request payload for POST /api/article/{articleId}/barcode.
 */
final class AddBarcodeDto
{
    #[Assert\NotBlank]
    public string $barcode;

    public function __construct(string $barcode)
    {
        $this->barcode = trim($barcode);
    }
}
