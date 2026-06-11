<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\AccountBalanceBoundaryException;
use App\Exception\ArticleInactiveException;
use App\Exception\ArticleNotFoundException;
use App\Exception\ParameterNotFoundException;
use App\Exception\TransactionBoundaryException;
use App\Exception\TransactionInvalidException;
use App\Exception\TransactionNotDeletableException;
use App\Exception\TransactionNotFoundException;
use App\Exception\UserNotFoundException;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\ORM\EntityManagerInterface;

class TransactionService
{
    public function __construct(
        private SettingsService $settingsService,
        private EntityManagerInterface $entityManager,
    ) {
        $connection = $this->entityManager->getConnection();
        if ($connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $connection->setTransactionIsolation(TransactionIsolationLevel::READ_COMMITTED);
        }
    }

    public function isDeletable(Transaction $transaction): bool
    {
        if ($transaction->isDeleted()) {
            return false;
        }

        if (!$this->settingsService->getOrDefault('payment.undo.enabled', false)) {
            return false;
        }

        $deletionTimeout = $this->settingsService->getOrDefault('payment.undo.timeout');
        if ($deletionTimeout) {
            $dateTime = new \DateTime();
            $dateTime->sub(\DateInterval::createFromDateString($deletionTimeout));

            if ($transaction->getCreated() < $dateTime) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws AccountBalanceBoundaryException
     * @throws TransactionBoundaryException
     * @throws TransactionInvalidException
     * @throws ParameterNotFoundException
     */
    public function doTransaction(User $user, ?int $amount, ?string $comment = null, ?int $quantity = 1, ?int $articleId = null, ?int $recipientId = null): Transaction
    {
        if (($recipientId || $articleId) && $amount > 0) {
            throw new TransactionInvalidException('Amount can\'t be positive when sending money or buying an article');
        }

        $senderId = $user->getId();

        return $this->entityManager->wrapInTransaction(function () use ($senderId, $amount, $comment, $quantity, $articleId, $recipientId) {
            $transaction = new Transaction();
            $transaction->setComment($comment);

            $userRepo = $this->entityManager->getRepository(User::class);

            $userIds = $recipientId ? [$senderId, $recipientId] : [$senderId];
            sort($userIds, SORT_NUMERIC);

            $lockedUsers = [];
            foreach ($userIds as $id) {
                $u = $userRepo->find($id);
                if (!$u) {
                    throw new UserNotFoundException($id);
                }
                $this->lockAndRefresh($u);
                $lockedUsers[$id] = $u;
            }

            $article = null;
            if ($articleId) {
                $article = $this->entityManager->getRepository(Article::class)->find($articleId);
                if (!$article) {
                    throw new ArticleNotFoundException($articleId);
                }
                $this->lockAndRefresh($article);

                if (!$article->isActive()) {
                    throw new ArticleInactiveException($article);
                }

                $transaction->setQuantity($quantity ?: 1);

                if (null === $amount) {
                    $amount = $article->getAmount() * $transaction->getQuantity() * -1;
                }

                $transaction->setArticle($article);

                $article->incrementUsageCount();
                $this->entityManager->persist($article);
            }

            if ($recipientId) {
                $recipient = $lockedUsers[$recipientId];

                $recipientTransaction = new Transaction();
                $recipientTransaction->setAmount($amount * -1);
                $recipientTransaction->setArticle($article);
                $recipientTransaction->setComment($comment);
                $recipientTransaction->setUser($recipient);

                $recipientTransaction->setSenderTransaction($transaction);
                $transaction->setRecipientTransaction($recipientTransaction);

                $recipient->addBalance($amount * -1);
                $this->checkAccountBalanceBoundary($recipient);

                $this->entityManager->persist($recipientTransaction);
                $this->entityManager->persist($recipient);
            }

            $sender = $lockedUsers[$senderId];

            $transaction->setUser($sender);
            $transaction->setAmount($amount);
            $this->checkTransactionBoundary($amount);

            $sender->addBalance($amount);
            $this->checkAccountBalanceBoundary($sender);

            $this->entityManager->persist($transaction);
            $this->entityManager->persist($sender);

            return $transaction;
        });
    }

    /**
     * Deposit (positive) or dispense (negative) cents on a single user account.
     */
    public function createForUser(User $user, int $cents, ?string $comment = null): Transaction
    {
        return $this->doTransaction($user, $cents, $comment);
    }

    /**
     * Transfer `$cents` (negative — debits the sender) from $sender to $recipient.
     */
    public function transferBetween(User $sender, User $recipient, int $cents, ?string $comment = null): Transaction
    {
        $debit = $cents > 0 ? -$cents : $cents;

        return $this->doTransaction($sender, $debit, $comment, null, null, $recipient->getId());
    }

    public function purchaseArticle(User $user, Article $article, int $quantity = 1, ?string $comment = null): Transaction
    {
        return $this->doTransaction($user, null, $comment, $quantity, $article->getId());
    }

    /**
     * Atomic: relies on use_savepoints so the nested doTransaction calls roll
     * back as one unit. Locks all involved users in id order first so two
     * concurrent splits can't deadlock.
     *
     * @param User[] $participants
     * @param int[]  $perRowCents  debit per row in cents; sums to the operator's total
     *
     * @return Transaction[] sender-side transaction per participant
     *
     * @throws TransactionInvalidException
     */
    public function doSplit(array $participants, User $recipient, array $perRowCents, ?string $comment = null): array
    {
        if (0 === count($participants)) {
            throw new TransactionInvalidException('split needs at least one participant');
        }
        if (count($participants) !== count($perRowCents)) {
            throw new TransactionInvalidException('participants and perRowCents must align');
        }
        foreach ($perRowCents as $c) {
            if ($c <= 0) {
                throw new TransactionInvalidException('per-row amount must be a positive int');
            }
        }

        return $this->entityManager->wrapInTransaction(function () use ($participants, $recipient, $perRowCents, $comment) {
            // take every lock upfront; doTransaction's own locking is then a no-op
            $allIds = array_unique(array_merge(
                [$recipient->getId()],
                array_map(fn (User $u) => $u->getId(), $participants),
            ));
            sort($allIds, SORT_NUMERIC);
            $userRepo = $this->entityManager->getRepository(User::class);
            foreach ($allIds as $uid) {
                $u = $userRepo->find($uid);
                if (null !== $u) {
                    $this->lockAndRefresh($u);
                }
            }

            $results = [];
            foreach ($participants as $idx => $participant) {
                $results[] = $this->doTransaction(
                    $participant,
                    -$perRowCents[$idx],
                    $comment,
                    null,
                    null,
                    $recipient->getId(),
                );
            }

            return $results;
        });
    }

    /**
     * @throws AccountBalanceBoundaryException
     * @throws TransactionBoundaryException
     * @throws TransactionInvalidException
     * @throws ParameterNotFoundException
     */
    /**
     * @throws TransactionNotFoundException
     * @throws TransactionNotDeletableException
     */
    public function revertTransaction(int $transactionId): Transaction
    {
        return $this->entityManager->wrapInTransaction(function () use ($transactionId) {
            $txRepo = $this->entityManager->getRepository(Transaction::class);

            $primaryTx = $txRepo->find($transactionId);
            if (!$primaryTx) {
                throw new TransactionNotFoundException($transactionId);
            }

            $transactionIds = [$primaryTx->getId()];
            $userIds = [$primaryTx->getUser()->getId()];

            $pairedTx = $primaryTx->getRecipientTransaction() ?? $primaryTx->getSenderTransaction();
            if ($pairedTx) {
                $transactionIds[] = $pairedTx->getId();
                $userIds[] = $pairedTx->getUser()->getId();
            }

            sort($transactionIds, SORT_NUMERIC);
            sort($userIds, SORT_NUMERIC);

            $lockedTx = [];
            foreach ($transactionIds as $id) {
                $t = $txRepo->find($id);
                if (!$t) {
                    throw new TransactionNotFoundException($id);
                }
                $this->lockAndRefresh($t);
                $lockedTx[$id] = $t;
            }

            $userRepo = $this->entityManager->getRepository(User::class);
            foreach ($userIds as $id) {
                $u = $userRepo->find($id);
                if (!$u) {
                    throw new UserNotFoundException($id);
                }
                $this->lockAndRefresh($u);
            }

            $articleRef = $primaryTx->getArticle();
            if ($articleRef) {
                $article = $this->entityManager->getRepository(Article::class)->find($articleRef->getId());
                if (!$article) {
                    throw new ArticleNotFoundException($articleRef->getId());
                }
                $this->lockAndRefresh($article);

                $article->decrementUsageCount();
                $this->entityManager->persist($article);
            }

            foreach ($lockedTx as $t) {
                if ($t->isDeleted()) {
                    throw new TransactionNotDeletableException($t);
                }
                $this->undoTransaction($t);
            }

            return $lockedTx[$transactionId];
        });
    }

    /**
     * @throws AccountBalanceBoundaryException
     * @throws TransactionBoundaryException
     * @throws TransactionInvalidException
     * @throws ParameterNotFoundException
     */
    private function undoTransaction(Transaction $transaction): void
    {
        $user = $transaction->getUser();

        // no boundary re-check: limits tightened after the fact must not block a reversal
        $user->addBalance($transaction->getAmount() * -1);
        $this->checkAccountBalanceBoundary($user);

        if ($this->settingsService->getOrDefault('payment.undo.delete', false)) {
            $this->entityManager->remove($transaction);
        } else {
            $transaction->setDeleted(true);
            $this->entityManager->persist($transaction);
        }

        $this->entityManager->persist($user);
    }

    /**
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Doctrine\ORM\PessimisticLockException
     */
    private function lockAndRefresh(object $entity): void
    {
        $this->entityManager->lock($entity, LockMode::PESSIMISTIC_WRITE);
        $this->entityManager->refresh($entity);
    }

    /**
     * @throws TransactionBoundaryException
     * @throws TransactionInvalidException
     * @throws ParameterNotFoundException
     */
    private function checkTransactionBoundary(?int $amount): void
    {
        if (!$amount) {
            throw new TransactionInvalidException();
        }

        $upper = $this->settingsService->getOrDefault('payment.boundary.upper', false);
        if (false !== $upper && $amount > $upper) {
            throw new TransactionBoundaryException($amount, $upper);
        }

        $lower = $this->settingsService->getOrDefault('payment.boundary.lower', false);
        if (false !== $lower && $amount < $lower) {
            throw new TransactionBoundaryException($amount, $lower);
        }
    }

    /**
     * @throws AccountBalanceBoundaryException
     * @throws ParameterNotFoundException
     */
    private function checkAccountBalanceBoundary(User $user): void
    {
        $balance = $user->getBalance();

        $upper = $this->settingsService->getOrDefault('account.boundary.upper', false);
        if (false !== $upper && $balance > $upper) {
            throw new AccountBalanceBoundaryException($user, $balance, $upper);
        }

        $lower = $this->settingsService->getOrDefault('account.boundary.lower', false);
        if (false !== $lower && $balance < $lower) {
            throw new AccountBalanceBoundaryException($user, $balance, $lower);
        }
    }
}
