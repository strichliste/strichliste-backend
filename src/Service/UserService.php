<?php

namespace App\Service;

use App\Entity\User;
use App\Event\UserCreatedEvent;
use App\Event\UserUpdatedEvent;
use App\Exception\ParameterNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class UserService
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function isActive(User $user): bool
    {
        $staleDateTime = $this->getStaleDateTime();
        if ($staleDateTime) {
            return null !== $user->getUpdated() && $user->getUpdated() >= $staleDateTime;
        }

        return true;
    }

    public function getStaleDateTime(): ?\DateTime
    {
        try {
            $configValue = $this->settingsService->get('user.stalePeriod');

            $period = \DateInterval::createFromDateString($configValue);

            $since = new \DateTime();

            return $since->sub($period);
        } catch (ParameterNotFoundException) {
            return null;
        }
    }

    /**
     * Persist a newly built user and announce it. Name de-duplication stays with
     * the caller; a lost unique-name race still surfaces as the usual
     * UniqueConstraintViolationException from flush() — before any event fires.
     */
    public function create(User $user): User
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new UserCreatedEvent($user));

        return $user;
    }

    /**
     * Persist changes to an existing user and announce them.
     */
    public function update(User $user): User
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new UserUpdatedEvent($user));

        return $user;
    }
}
