<?php

namespace App\Serializer;

use App\Dto\Api\User as UserDto;
use App\Entity\User;
use App\Service\UserService;

class UserSerializer
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function serialize(User $user): UserDto
    {
        // entity getters are ?int/?string (Doctrine nullability), but a persisted user
        // always has them and the contract types are non-null — the casts narrow to the
        // wire shape deliberately; do not "simplify" them back to nullable.
        return new UserDto(
            id: (int) $user->getId(),
            name: (string) $user->getName(),
            email: $user->getEmail(),
            balance: $user->getBalance(),
            isActive: $this->userService->isActive($user),
            isDisabled: $user->isDisabled(),
            created: $user->getCreated()->format('Y-m-d H:i:s'),
            updated: $user->getUpdated()?->format('Y-m-d H:i:s'),
        );
    }
}
