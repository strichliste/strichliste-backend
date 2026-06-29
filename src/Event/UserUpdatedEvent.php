<?php

namespace App\Event;

use App\Entity\User;

/**
 * A user's profile was updated. Dispatched once, after the update has been committed.
 */
final class UserUpdatedEvent
{
    public function __construct(public readonly User $user)
    {
    }
}
