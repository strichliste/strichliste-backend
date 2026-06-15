<?php

namespace App\Dto\Api;

use App\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request payload for POST /api/user.
 *
 * Mapped from JSON or form-encoded bodies via #[MapRequestPayload]; the
 * #[Assert] constraints drive both validation and the generated OpenAPI schema.
 * The name is sanitized on construction so validation runs on the stored value.
 */
#[OA\Schema(required: ['name'])]
final class CreateUserDto
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    public string $name;

    #[Assert\Email]
    #[Assert\Length(max: 255)]
    #[OA\Property(format: 'email')]
    public ?string $email;

    public function __construct(?string $name = null, ?string $email = null)
    {
        // missing -> '' so NotBlank reports it as a clean validation failure
        $this->name = User::sanitizeName($name ?? '');
        $this->email = null === $email ? null : trim($email);
    }
}
