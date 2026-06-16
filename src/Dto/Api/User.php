<?php

namespace App\Dto\Api;

use OpenApi\Attributes as OA;

/**
 * Response payload for the frozen /api `user` shape.
 *
 * Built by {@see \App\Serializer\UserSerializer}, emitted by the #[Serialize]
 * controllers and read by Nelmio for the OpenAPI schema — one class is both the
 * runtime projection and the documented contract, pinned by tests/Controller/Api/*.
 */
final class User
{
    public function __construct(
        #[OA\Property(example: 1)]
        public int $id,
        #[OA\Property(example: 'Bob')]
        public string $name,
        #[OA\Property(format: 'email', example: 'bob@example.com')]
        public ?string $email,
        #[OA\Property(description: 'Balance in cents.', example: 1337)]
        public int $balance,
        #[OA\Property(description: 'Had a transaction within the staleness window.')]
        public bool $isActive,
        public bool $isDisabled,
        #[OA\Property(example: '2026-06-10 12:34:56')]
        public string $created,
        #[OA\Property(example: '2026-06-10 12:34:56')]
        public ?string $updated,
    ) {
    }
}
