<?php

namespace App\Event;

use App\Entity\User;

/**
 * A user was created. Dispatched once, after the create has been committed.
 */
final class UserCreatedEvent
{
    public function __construct(public readonly User $user)
    {
    }
}
