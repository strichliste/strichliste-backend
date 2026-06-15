<?php

namespace App\Dto\Api;

use OpenApi\Attributes as OA;

/**
 * OpenAPI response model for the frozen /api `user` shape.
 *
 * Documentation-only: mirrors {@see \App\Serializer\UserSerializer} and is
 * pinned by tests/Controller/Api/*. Never instantiated — the typed properties
 * exist so Nelmio derives the schema from them.
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
