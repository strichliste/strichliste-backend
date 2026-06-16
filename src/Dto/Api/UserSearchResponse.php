<?php

namespace App\Dto\Api;

/**
 * Envelope for the user search: `{ "count": N, "users": [ … ] }`.
 */
final class UserSearchResponse
{
    /**
     * @param list<User> $users
     */
    public function __construct(
        public int $count,
        public array $users,
    ) {
    }
}
