<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\ParameterNotFoundException;

class UserService {

    /**
     * @var SettingsService
     */
    private $settingsService;

    function __construct(SettingsService $settingsService) {
        $this->settingsService = $settingsService;
    }

    function isActive(User $user): bool {
        $staleDateTime = $this->getStaleDateTime();
        if ($staleDateTime) {
            return ($user->getUpdated() !== null && $user->getUpdated() > $staleDateTime);
        }

        return true;
    }

    function getStaleDateTime(): ?\DateTime {
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