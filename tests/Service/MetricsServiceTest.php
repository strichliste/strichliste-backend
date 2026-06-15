<?php

namespace App\Tests\Service;

use App\Entity\Transaction;
use App\Entity\User;
use App\Service\MetricsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Direct service test for MetricsService against a real EntityManager.
 * dama/doctrine-test-bundle rolls back the DB after each test.
 */
class MetricsServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MetricsService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(MetricsService::class);
    }

    private function createUser(string $name, int $balance = 0, bool $disabled = false): User
    {
        $user = new User();
        $user->setName($name);
        $user->setBalance($balance);
        $user->setDisabled($disabled);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function addTransaction(User $user, int $amount): Transaction
    {
        $tx = new Transaction();
        $tx->setUser($user);
        $tx->setAmount($amount);
        $this->em->persist($tx);
        $this->em->flush();

        return $tx;
    }

    public function testTotalBalanceExcludesDisabledUsers(): void
    {
        $this->createUser('ActiveA', 500);
        $this->createUser('ActiveB', 300);
        $this->createUser('Disabled', 10000, true);

        self::assertSame(800, $this->service->totalBalance());
    }

    public function testTransactionsPerDayUiVsApiShapeDivergence(): void
    {
        $user = $this->createUser('Spender');
        $this->addTransaction($user, 500);  // charged
        $this->addTransaction($user, -200); // spent

        $today = date('Y-m-d');

        $ui = array_column($this->service->transactionsPerDay(3, 'ui'), null, 'date')[$today];
        $api = array_column($this->service->transactionsPerDay(3, 'api'), null, 'date')[$today];

        // ui: scalar charged/spent
        self::assertSame(500, $ui['charged']);
        self::assertSame(200, $ui['spent'], 'spent is reported as a positive magnitude');

        // api: nested {amount, transactions}
        self::assertSame(['amount' => 500, 'transactions' => 1], $api['charged']);
        self::assertSame(['amount' => 200, 'transactions' => 1], $api['spent']);

        // balance (net) and distinct users are shape-independent
        self::assertSame(300, $ui['balance']);
        self::assertSame(300, $api['balance']);
        self::assertSame(1, $ui['distinctUsers']);
    }

    public function testTransactionsPerDayCountIsTodayPlusPriorDays(): void
    {
        // empty DB: still returns today + $days prior rows, all scalar zeroes (ui shape)
        $rows = $this->service->transactionsPerDay(3, 'ui');
        self::assertCount(4, $rows);

        foreach ($rows as $row) {
            self::assertSame(0, $row['transactions']);
            self::assertSame(0, $row['charged']);
            self::assertSame(0, $row['spent']);
        }
    }

    public function testUserOutgoingAndIncomingZeroCasesDoNotError(): void
    {
        // a user with no transfers at all must yield zeroed aggregates, not a query error
        $user = $this->createUser('NoTransfers');

        self::assertSame(['cnt' => 0, 'amount' => 0], $this->service->userOutgoing($user));
        self::assertSame(['cnt' => 0, 'amount' => 0], $this->service->userIncoming($user));
    }

    public function testTotalCountsReflectSeededData(): void
    {
        $a = $this->createUser('CountA');
        $b = $this->createUser('CountB');
        $this->addTransaction($a, 100);
        $this->addTransaction($a, -50);
        $this->addTransaction($b, 200);

        self::assertSame(3, $this->service->totalTransactionCount());
        self::assertSame(2, $this->service->totalUserCount());
        self::assertSame(2, $this->service->userTransactionCount($a));
    }
}
