<?php

namespace App\Tests\Service;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\AccountBalanceBoundaryException;
use App\Exception\ArticleInactiveException;
use App\Exception\TransactionBoundaryException;
use App\Exception\TransactionInvalidException;
use App\Exception\TransactionNotDeletableException;
use App\Exception\TransactionNotFoundException;
use App\Service\SettingsService;
use App\Service\TransactionService;
use App\Tests\Support\TestSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TransactionServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Builds the service directly so each test picks its own settings —
     * strichliste.yaml defaults plus $overrides.
     *
     * @param array<string, mixed> $overrides
     */
    private function service(array $overrides = []): TransactionService
    {
        $settings = static::getContainer()->getParameter('strichliste');
        \assert(\is_array($settings));

        return new TransactionService(
            new SettingsService(TestSettingsService::mergeSettings($settings, $overrides)),
            $this->em,
        );
    }

    private function createUser(string $name, int $balance = 0): User
    {
        $user = new User();
        $user->setName($name);
        $user->setBalance($balance);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createArticle(string $name, int $cents): Article
    {
        $article = new Article();
        $article->setName($name);
        $article->setAmount($cents);
        $this->em->persist($article);
        $this->em->flush();

        return $article;
    }

    /**
     * A failed wrapInTransaction closes the EntityManager, so post-exception
     * assertions read through a reset manager (the DAMA test transaction and
     * its data survive — only the manager is replaced).
     */
    private function freshEm(): EntityManagerInterface
    {
        $registry = static::getContainer()->get('doctrine');
        \assert($registry instanceof ManagerRegistry);
        $registry->resetManager();

        $em = $registry->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    private function freshBalance(EntityManagerInterface $em, ?int $userId): int
    {
        \assert(null !== $userId);
        $user = $em->find(User::class, $userId);
        \assert(null !== $user);

        return $user->getBalance();
    }

    public function testPositiveAmountWithRecipientIsInvalid(): void
    {
        $alice = $this->createUser('Alice');
        $bob = $this->createUser('Bob');

        $this->expectException(TransactionInvalidException::class);
        $this->service()->doTransaction($alice, 500, recipientId: $bob->getId());
    }

    public function testZeroAmountIsInvalid(): void
    {
        $alice = $this->createUser('Alice');

        $this->expectException(TransactionInvalidException::class);
        $this->service()->createForUser($alice, 0);
    }

    public function testPaymentBoundaryUpper(): void
    {
        $alice = $this->createUser('Alice');

        // payment.boundary.upper is 15000
        $this->expectException(TransactionBoundaryException::class);
        $this->service()->createForUser($alice, 15001);
    }

    public function testPaymentBoundaryLower(): void
    {
        $alice = $this->createUser('Alice');

        // payment.boundary.lower is -2000
        $this->expectException(TransactionBoundaryException::class);
        $this->service()->createForUser($alice, -2001);
    }

    public function testExplicitFalseDisablesAPaymentBoundary(): void
    {
        // SettingsService::get() uses isset, so `upper: false` is found and
        // checkTransactionBoundary skips the comparison entirely
        $alice = $this->createUser('Alice');

        $this->service(['payment' => ['boundary' => ['upper' => false]]])->createForUser($alice, 19999);

        $this->assertSame(19999, $alice->getBalance());
    }

    public function testAccountBoundaryUpper(): void
    {
        $alice = $this->createUser('Alice');
        $service = $this->service();
        $service->createForUser($alice, 14000);

        // 14000 + 14000 crosses account.boundary.upper (20000)
        $this->expectException(AccountBalanceBoundaryException::class);
        $service->createForUser($alice, 14000);
    }

    public function testAccountBoundaryLower(): void
    {
        $alice = $this->createUser('Alice', -19000);

        // -2000 passes the payment boundary (not strictly below it) but the
        // resulting balance crosses account.boundary.lower
        $this->expectException(AccountBalanceBoundaryException::class);
        $this->service()->createForUser($alice, -2000);
    }

    public function testTransferPairsTheTransactions(): void
    {
        $alice = $this->createUser('Alice');
        $bob = $this->createUser('Bob');

        // a positive amount is normalized into a debit of the sender
        $tx = $this->service()->transferBetween($alice, $bob, 500, 'pizza');

        $this->assertSame(-500, $tx->getAmount());
        $this->assertSame($alice->getId(), $tx->getUser()->getId());

        $paired = $tx->getRecipientTransaction();
        $this->assertNotNull($paired);
        $this->assertSame(500, $paired->getAmount());
        $this->assertSame($bob->getId(), $paired->getUser()->getId());
        $this->assertSame($tx->getId(), $paired->getSenderTransaction()?->getId());

        // the comment travels to both sides of the pair
        $this->assertSame('pizza', $tx->getComment());
        $this->assertSame('pizza', $paired->getComment());

        $this->assertSame(-500, $alice->getBalance());
        $this->assertSame(500, $bob->getBalance());
    }

    public function testPurchaseDebitsPriceTimesQuantity(): void
    {
        $alice = $this->createUser('Alice');
        $article = $this->createArticle('Club Mate', 150);

        $tx = $this->service()->purchaseArticle($alice, $article, 3);

        $this->assertSame(-450, $tx->getAmount());
        $this->assertSame(3, $tx->getQuantity());
        $this->assertSame(-450, $alice->getBalance());
        // one purchase, regardless of quantity
        $this->assertSame(1, $article->getUsageCount());
    }

    public function testPurchaseOfInactiveArticleThrows(): void
    {
        $alice = $this->createUser('Alice');
        $article = $this->createArticle('Club Mate', 150);
        $article->setActive(false);
        $this->em->flush();

        $this->expectException(ArticleInactiveException::class);
        $this->service()->purchaseArticle($alice, $article);
    }

    public function testIsDeletableMatrix(): void
    {
        $alice = $this->createUser('Alice');
        $service = $this->service();
        $tx = $service->createForUser($alice, 500);

        $this->assertTrue($service->isDeletable($tx), 'fresh transaction inside the window');

        $tx->setCreated(new \DateTime('-10 minutes'));
        $this->assertFalse($service->isDeletable($tx), 'outside the 5 minute window');

        $this->assertTrue(
            $this->service(['payment' => ['undo' => ['timeout' => false]]])->isDeletable($tx),
            'no timeout configured: age does not matter',
        );

        $this->assertFalse(
            $this->service(['payment' => ['undo' => ['enabled' => false]]])->isDeletable($tx),
            'undo disabled beats everything',
        );

        $tx->setCreated(new \DateTime());
        $tx->setDeleted(true);
        $this->assertFalse($service->isDeletable($tx), 'already reverted');
    }

    public function testRevertTransferRestoresBothBalances(): void
    {
        $alice = $this->createUser('Alice');
        $bob = $this->createUser('Bob');
        $service = $this->service();
        $tx = $service->transferBetween($alice, $bob, 500);
        \assert(null !== $tx->getId());

        $reverted = $service->revertTransaction($tx->getId());

        $this->assertTrue($reverted->isDeleted());
        $this->assertTrue($tx->getRecipientTransaction()?->isDeleted());
        $this->assertSame(0, $alice->getBalance());
        $this->assertSame(0, $bob->getBalance());
    }

    public function testRevertPurchaseRestoresUsageCount(): void
    {
        $alice = $this->createUser('Alice');
        $article = $this->createArticle('Club Mate', 150);
        $service = $this->service();
        $tx = $service->purchaseArticle($alice, $article);
        \assert(null !== $tx->getId());

        $service->revertTransaction($tx->getId());

        $this->assertSame(0, $alice->getBalance());
        $this->assertSame(0, $article->getUsageCount());
    }

    public function testRevertOfUnknownIdThrows(): void
    {
        $this->expectException(TransactionNotFoundException::class);
        $this->service()->revertTransaction(999999);
    }

    public function testRevertTwiceThrows(): void
    {
        $alice = $this->createUser('Alice');
        $service = $this->service();
        $tx = $service->createForUser($alice, 500);
        \assert(null !== $tx->getId());
        $service->revertTransaction($tx->getId());

        $this->expectException(TransactionNotDeletableException::class);
        $service->revertTransaction($tx->getId());
    }

    public function testUndoSoftDeleteKeepsTheRow(): void
    {
        $alice = $this->createUser('Alice');
        $service = $this->service();
        $tx = $service->createForUser($alice, 500);
        $txId = $tx->getId();
        \assert(null !== $txId);

        $service->revertTransaction($txId);

        $this->em->clear();
        $found = $this->em->find(Transaction::class, $txId);
        $this->assertNotNull($found);
        $this->assertTrue($found->isDeleted());
    }

    public function testUndoHardDeleteRemovesTheRow(): void
    {
        $alice = $this->createUser('Alice');
        $service = $this->service(['payment' => ['undo' => ['delete' => true]]]);
        $tx = $service->createForUser($alice, 500);
        $txId = $tx->getId();
        \assert(null !== $txId);

        $service->revertTransaction($txId);

        $this->em->clear();
        $this->assertNull($this->em->find(Transaction::class, $txId));
        $this->assertSame(0, $this->freshBalance($this->em, $alice->getId()));
    }

    public function testRevertSkipsThePaymentBoundary(): void
    {
        // limits tightened after the fact must not block a reversal: the
        // 15000 deposit reverts fine under a service capped at 100
        $alice = $this->createUser('Alice');
        $tx = $this->service()->createForUser($alice, 15000);
        \assert(null !== $tx->getId());

        $this->service(['payment' => ['boundary' => ['upper' => 100, 'lower' => -100]]])
            ->revertTransaction($tx->getId());

        $this->assertSame(0, $alice->getBalance());
    }

    public function testRevertStillChecksTheAccountBoundary(): void
    {
        $alice = $this->createUser('Alice');
        $service = $this->service();
        $tx = $service->createForUser($alice, 15000);
        $txId = $tx->getId();
        \assert(null !== $txId);

        // the money has been spent meanwhile; reversing the deposit would
        // push the account below account.boundary.lower (-20000)
        $alice->setBalance(-6000);
        $this->em->flush();

        try {
            $service->revertTransaction($txId);
            $this->fail('expected the revert to hit the account boundary');
        } catch (AccountBalanceBoundaryException) {
        }

        // the balance is mutated before the boundary check throws — only the
        // transaction rollback keeps the DB clean, so pin it
        $em = $this->freshEm();
        $this->assertSame(-6000, $this->freshBalance($em, $alice->getId()));
        $this->assertFalse($em->find(Transaction::class, $txId)?->isDeleted());
    }

    public function testSplitNeedsParticipants(): void
    {
        $alice = $this->createUser('Alice');

        $this->expectException(TransactionInvalidException::class);
        $this->service()->doSplit([], $alice, []);
    }

    public function testSplitNeedsAlignedAmounts(): void
    {
        $alice = $this->createUser('Alice');
        $bob = $this->createUser('Bob');

        $this->expectException(TransactionInvalidException::class);
        $this->service()->doSplit([$bob], $alice, [500, 500]);
    }

    public function testSplitNeedsPositiveRowAmounts(): void
    {
        $alice = $this->createUser('Alice');
        $bob = $this->createUser('Bob');

        $this->expectException(TransactionInvalidException::class);
        $this->service()->doSplit([$bob], $alice, [0]);
    }

    public function testSplitRollsBackAllRowsOnBoundaryViolation(): void
    {
        $alice = $this->createUser('Alice');
        $bob = $this->createUser('Bob');
        $carol = $this->createUser('Carol', -19500);

        try {
            // Bob's row books fine; Carol's crosses account.boundary.lower
            $this->service()->doSplit([$bob, $carol], $alice, [1500, 1500]);
            $this->fail('expected the split to hit the account boundary');
        } catch (AccountBalanceBoundaryException) {
        }

        $em = $this->freshEm();
        $this->assertSame(0, $this->freshBalance($em, $alice->getId()));
        $this->assertSame(0, $this->freshBalance($em, $bob->getId()));
        $this->assertSame(-19500, $this->freshBalance($em, $carol->getId()));
        $this->assertSame(0, $em->getRepository(Transaction::class)->count([]));
    }
}
