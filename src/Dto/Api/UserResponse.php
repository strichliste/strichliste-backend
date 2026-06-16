<?php

namespace App\Dto\Api;

/**
 * Envelope for endpoints returning a single user: `{ "user": { … } }`.
 */
final class UserResponse
{
    public function __construct(
        public User $user,
    ) {
    }
}
