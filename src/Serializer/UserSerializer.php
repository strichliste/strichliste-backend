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
        return new UserDto(
            id: $user->getId(),
            name: $user->getName(),
            email: $user->getEmail(),
            balance: $user->getBalance(),
            isActive: $this->userService->isActive($user),
            isDisabled: $user->isDisabled(),
            created: $user->getCreated()->format('Y-m-d H:i:s'),
            updated: $user->getUpdated()?->format('Y-m-d H:i:s'),
        );
    }
}
