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

    // non-null $name with a default (not ?string) so the schema renders non-null
    // without an #[OA\Property(nullable: false)] override; a missing name still
    // arrives as '' and fails NotBlank as a clean 422.
    public function __construct(string $name = '', ?string $email = null)
    {
        $this->name = User::sanitizeName($name);
        $this->email = null === $email ? null : trim($email);
    }
}
