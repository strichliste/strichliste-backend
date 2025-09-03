<?php

namespace App\Serializer;

use App\Entity\User;
use App\Service\UserService;
use DateTimeInterface;

class UserSerializer {
    public function __construct(private readonly UserService $userService) {}

    public function serialize(User $user): array {
        return [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'balance' => $user->getBalance(),
            'isActive' => $this->userService->isActive($user),
            'isDisabled' => $user->isDisabled(),
            'created' => $user->getCreated()->format('Y-m-d H:i:s'),
            'updated' => $user->getUpdated() instanceof DateTimeInterface ? $user->getUpdated()->format('Y-m-d H:i:s') : null,
        ];
    }
}
