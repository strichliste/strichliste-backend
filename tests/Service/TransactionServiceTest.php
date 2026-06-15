<?php

namespace App\Tests\Service;

use App\Entity\Article;
use App\Entity\User;
use App\Exception\AccountBalanceBoundaryException;
use App\Exception\TransactionBoundaryException;
use App\Exception\TransactionInvalidException;
use App\Service\TransactionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Direct service test: real EntityManager + the container's TransactionService
 * (so the configured payment/account boundaries from config/strichliste.yaml
 * apply). dama/doctrine-test-bundle rolls back the DB after each test.
 *
 * Config boundaries in play:
 *   payment.boundary.upper = 15000, lower = -2000
 *   account.boundary.upper = 20000, lower = -20000
 */
class TransactionServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private TransactionService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(TransactionService::class);
    }

    private function createUser(string $name): User
    {
        $user = new User();
        $user->setName($name);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createArticle(string $name, int $amount): Article
    {
        $article = new Article();
        $article->setName($name);
        $article->setAmount($amount);
        $this->em->persist($article);
        $this->em->flush();

        return $article;
    }

    public function testAmountExactlyAtUpperBoundaryPasses(): void
    {
        $user = $this->createUser('UpperEdge');

        $tx = $this->service->createForUser($user, 15000);

        self::assertSame(15000, $tx->getAmount());
        self::assertSame(15000, $user->getBalance());
    }

    public function testAmountExactlyAtLowerBoundaryPasses(): void
    {
        $user = $this->createUser('LowerEdge');

        $tx = $this->service->createForUser($user, -2000);

        self::assertSame(-2000, $tx->getAmount());
        self::assertSame(-2000, $user->getBalance());
    }

    public function testOneCentPastUpperBoundaryThrows(): void
    {
        $user = $this->createUser('OverUpper');

        $this->expectException(TransactionBoundaryException::class);
        $this->service->createForUser($user, 15001);
    }

    public function testOneCentPastLowerBoundaryThrows(): void
    {
        $user = $this->createUser('OverLower');

        $this->expectException(TransactionBoundaryException::class);
        $this->service->createForUser($user, -2001);
    }

    public function testAmountZeroThrowsTransactionInvalid(): void
    {
        $user = $this->createUser('ZeroAmount');

        $this->expectException(TransactionInvalidException::class);
        $this->service->createForUser($user, 0);
    }

    public function testNormalTransferDebitsSenderAndCreditsRecipient(): void
    {
        $sender = $this->createUser('Sender');
        $recipient = $this->createUser('Recipient');

        // give the sender funds to transfer
        $this->service->createForUser($sender, 5000);

        $this->service->transferBetween($sender, $recipient, 1200);

        self::assertSame(3800, $sender->getBalance(), 'sender debited by 1200');
        self::assertSame(1200, $recipient->getBalance(), 'recipient credited by 1200');
    }

    public function testTransferPastAccountBoundaryThrowsAndLeavesBothBalancesUnchanged(): void
    {
        // recipient is already near the upper account boundary (20000); a transfer
        // that would push them over must roll back BOTH sides atomically.
        $sender = $this->createUser('AtomicSender');
        $recipient = $this->createUser('AtomicRecipient');

        // fund the sender enough to cover the transfer (stay within its own limits)
        $this->service->createForUser($sender, 10000);
        $this->service->createForUser($sender, 5000);
        $recipient->setBalance(19500);
        $this->em->flush();

        $senderId = $sender->getId();
        $recipientId = $recipient->getId();
        $senderBefore = $sender->getBalance();
        $recipientBefore = $recipient->getBalance();

        try {
            // 1000 credit would put recipient at 20500 > 20000 upper boundary
            $this->service->transferBetween($sender, $recipient, 1000);
            self::fail('expected AccountBalanceBoundaryException');
        } catch (AccountBalanceBoundaryException) {
            // expected
        }

        // a failed flush closes the ORM EntityManager; a real request would get a
        // fresh one, so re-read the persisted balances on a clean manager.
        if (!$this->em->isOpen()) {
            $manager = static::getContainer()->get('doctrine')->resetManager();
            self::assertInstanceOf(EntityManagerInterface::class, $manager);
            $this->em = $manager;
        }
        $userRepo = $this->em->getRepository(User::class);
        $senderAfter = $userRepo->find($senderId);
        $recipientAfter = $userRepo->find($recipientId);
        self::assertInstanceOf(User::class, $senderAfter);
        self::assertInstanceOf(User::class, $recipientAfter);

        self::assertSame($senderBefore, $senderAfter->getBalance(), 'sender balance must be unchanged after rollback');
        self::assertSame($recipientBefore, $recipientAfter->getBalance(), 'recipient balance must be unchanged after rollback');
    }

    public function testPurchaseWithQuantityDebitsQuantityTimesPriceAndIncrementsUsage(): void
    {
        $user = $this->createUser('Buyer');
        $this->service->createForUser($user, 5000);
        $article = $this->createArticle('Club Mate', 150);

        $tx = $this->service->purchaseArticle($user, $article, 3);

        self::assertSame(-450, $tx->getAmount(), '3 x 150 debited');
        self::assertSame(3, $tx->getQuantity());
        self::assertSame(4550, $user->getBalance());

        $this->em->refresh($article);
        self::assertSame(1, $article->getUsageCount(), 'usage count increments once per purchase, not per unit');
    }
}
