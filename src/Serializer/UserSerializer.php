<?php

namespace App\Serializer;

use App\Entity\User;

class UserSerializer {

    function serialize(User $user): array {
        return [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'active' => $user->isActive(),
            'email' => $user->getEmail(),
            'balance' => $user->getBalance(),
            'created' => $user->getCreated()->format('Y-m-d H:i:s'),
            'updated' => $user->getUpdated() ? $user->getUpdated()->format('Y-m-d H:i:s') : null
        ];
    }
}