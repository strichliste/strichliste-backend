<?php

namespace App\Dto\Api;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * Envelope for the user search: `{ "count": N, "users": [ … ] }`.
 */
final class UserSearchResponse
{
    /**
     * @param User[] $users
     */
    public function __construct(
        public int $count,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: User::class)))]
        public array $users,
    ) {
    }
}
