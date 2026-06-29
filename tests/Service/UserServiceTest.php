<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\SettingsService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The staleness logic only depends on SettingsService (a plain array wrapper),
 * so it is built directly; the persistence collaborators are mocked because
 * isActive()/getStaleDateTime() never touch them.
 */
class UserServiceTest extends TestCase
{
    private function serviceWithStalePeriod(?string $stalePeriod): UserService
    {
        $settings = null === $stalePeriod
            ? new SettingsService([])
            : new SettingsService(['user' => ['stalePeriod' => $stalePeriod]]);

        return new UserService(
            $settings,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(EventDispatcherInterface::class),
        );
    }

    private function userUpdatedAt(\DateTime $updated): User
    {
        $user = new User();
        $user->setName('subject');
        $user->setUpdated($updated);

        return $user;
    }

    public function testUserWithinStalePeriodIsActive(): void
    {
        $service = $this->serviceWithStalePeriod('10 day');
        // updated 5 days ago — comfortably inside the 10 day window
        $user = $this->userUpdatedAt(new \DateTime('-5 day'));

        self::assertTrue($service->isActive($user));
    }

    public function testUserJustOutsideStalePeriodIsInactive(): void
    {
        $service = $this->serviceWithStalePeriod('10 day');
        // updated 11 days ago — past the 10 day window
        $user = $this->userUpdatedAt(new \DateTime('-11 day'));

        self::assertFalse($service->isActive($user));
    }

    public function testUserUpdatedNowIsActive(): void
    {
        $service = $this->serviceWithStalePeriod('10 day');
        $user = $this->userUpdatedAt(new \DateTime());

        self::assertTrue($service->isActive($user));
    }

    public function testMissingStalePeriodMakesEveryoneActive(): void
    {
        $service = $this->serviceWithStalePeriod(null);
        // even a very old user is active when no stalePeriod is configured
        $user = $this->userUpdatedAt(new \DateTime('-10 year'));

        self::assertTrue($service->isActive($user));
        self::assertNull($service->getStaleDateTime());
    }

    public function testStaleDateTimeReflectsConfiguredPeriod(): void
    {
        $service = $this->serviceWithStalePeriod('10 day');

        $staleDateTime = $service->getStaleDateTime();
        self::assertInstanceOf(\DateTime::class, $staleDateTime);

        // roughly 10 days ago (allow a small window for clock drift during the test)
        $expected = new \DateTime()->sub(\DateInterval::createFromDateString('10 day'));
        self::assertEqualsWithDelta($expected->getTimestamp(), $staleDateTime->getTimestamp(), 5);
    }

    public function testUserWithNullUpdatedIsInactiveWhenStalePeriodSet(): void
    {
        // documents actual behavior: a never-updated user (updated === null) is
        // treated as inactive once a stalePeriod exists
        $service = $this->serviceWithStalePeriod('10 day');
        $user = new User();
        $user->setName('never-updated');

        self::assertFalse($service->isActive($user));
    }
}
