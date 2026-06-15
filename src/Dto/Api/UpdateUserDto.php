<?php

namespace App\Dto\Api;

use App\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request payload for POST /api/user/{userId}.
 *
 * Every field is optional — only the fields actually present are applied
 * (a null property means "not sent"). Name is sanitized and email trimmed on
 * construction so validation runs on the stored value.
 */
final class UpdateUserDto
{
    #[Assert\Length(max: 64)]
    public ?string $name;

    #[Assert\Email]
    #[Assert\Length(max: 255)]
    #[OA\Property(format: 'email')]
    public ?string $email;

    public function __construct(
        ?string $name = null,
        ?string $email = null,
        public ?bool $isDisabled = null,
    ) {
        $this->name = null === $name ? null : User::sanitizeName($name);
        $this->email = null === $email ? null : trim($email);
    }
}
