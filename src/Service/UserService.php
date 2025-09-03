<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\ParameterNotFoundException;
use DateInterval;
use DateTime;
use DateTimeInterface;

class UserService {
    public function __construct(private readonly SettingsService $settingsService) {}

    public function isActive(User $user): bool {
        $staleDateTime = $this->getStaleDateTime();
        if ($staleDateTime instanceof DateTime) {
            return $user->getUpdated() instanceof DateTimeInterface && $user->getUpdated() >= $staleDateTime;
        }

        return true;
    }

    public function getStaleDateTime(): ?DateTime {
        try {
            $configValue = $this->settingsService->get('user.stalePeriod');

            $period = DateInterval::createFromDateString($configValue);

            $since = new DateTime();

            return $since->sub($period);
        } catch (ParameterNotFoundException) {
            return null;
        }
    }
}
