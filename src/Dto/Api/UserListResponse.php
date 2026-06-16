<?php

namespace App\Dto\Api;

/**
 * Envelope for the user list: `{ "users": [ … ] }` (no count, by legacy contract).
 */
final class UserListResponse
{
    /**
     * @param list<User> $users
     */
    public function __construct(
        public array $users,
    ) {
    }
}
