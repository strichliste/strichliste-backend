<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\ParameterNotFoundException;

class UserService
{
    public function __construct(private SettingsService $settingsService)
    {
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
        } catch (ParameterNotFoundException $e) {
            return null;
        }
    }
}
