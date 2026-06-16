<?php

namespace App\Dto\Api;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * Envelope for the user list: `{ "users": [ … ] }` (no count, by legacy contract).
 */
final class UserListResponse
{
    /**
     * @param User[] $users
     */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: User::class)))]
        public array $users,
    ) {
    }
}
