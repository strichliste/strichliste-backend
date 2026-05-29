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

class TransactionService {

    function __construct(
        private SettingsService $settingsService,
        private EntityManagerInterface $entityManager,
    ) {
        $connection = $this->entityManager->getConnection();
        if ($connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $connection->setTransactionIsolation(TransactionIsolationLevel::READ_COMMITTED);
        }
    }


    function isDeletable(Transaction $transaction): bool {
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
     * @param User $user
     * @param int|null $amount
     * @param null|string $comment
     * @param int|null $quantity
     * @param int|null $articleId
     * @param int|null $recipientId
     * @throws AccountBalanceBoundaryException
     * @throws TransactionBoundaryException
     * @throws TransactionInvalidException
     * @throws ParameterNotFoundException
     * @return Transaction
     */
    function doTransaction(User $user, ?int $amount, ?string $comment = null, ?int $quantity = 1, ?int $articleId = null, ?int $recipientId = null): Transaction {

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

                if ($amount === null) {
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
     * Thin named facade over `doTransaction` so callers don't pass positional nulls.
     */
    public function createForUser(User $user, int $cents, ?string $comment = null): Transaction {
        return $this->doTransaction($user, $cents, $comment);
    }

    /**
     * Transfer `$cents` (negative — debits the sender) from $sender to $recipient.
     */
    public function transferBetween(User $sender, User $recipient, int $cents, ?string $comment = null): Transaction {
        $debit = $cents > 0 ? -$cents : $cents;
        return $this->doTransaction($sender, $debit, $comment, null, null, $recipient->getId());
    }

    /**
     * Buy $quantity copies of $article for $user; amount derived from article price.
     */
    public function purchaseArticle(User $user, Article $article, int $quantity = 1, ?string $comment = null): Transaction {
        return $this->doTransaction($user, null, $comment, $quantity, $article->getId());
    }

    /**
     * Atomically transfer per-row amounts from each participant to `$recipient`.
     * Wrapped in a single DB transaction; relies on `use_savepoints: true` for
     * the inner `doTransaction` calls to nest cleanly. Any failure rolls back
     * every transfer in the batch.
     *
     * The recipient is locked FIRST at the outer transaction level (in id-sorted
     * order with every participant), so two concurrent splits with overlapping
     * users acquire locks in the same order and can't deadlock.
     *
     * @param User[] $participants  non-empty list, one entry per row
     * @param int[]  $perRowCents   per-row debit amount in cents, indexed parallel
     *                              to $participants. Sum equals the operator's
     *                              total (caller distributes the remainder).
     * @return Transaction[] one transaction per participant (sender side)
     * @throws TransactionInvalidException if the inputs are inconsistent
     */
    function doSplit(array $participants, User $recipient, array $perRowCents, ?string $comment = null): array {
        if (count($participants) === 0) {
            throw new TransactionInvalidException('split needs at least one participant');
        }
        if (count($participants) !== count($perRowCents)) {
            throw new TransactionInvalidException('participants and perRowCents must align');
        }
        foreach ($perRowCents as $c) {
            if (!is_int($c) || $c <= 0) {
                throw new TransactionInvalidException('per-row amount must be a positive int');
            }
        }

        return $this->entityManager->wrapInTransaction(function () use ($participants, $recipient, $perRowCents, $comment) {
            // Acquire every lock upfront in sorted order to flatten deadlock
            // risk. doTransaction below also locks its own (sender, recipient)
            // pair — that re-lock is a no-op under savepoints because the row
            // is already locked by this outer transaction.
            $allIds = array_unique(array_merge(
                [$recipient->getId()],
                array_map(fn(User $u) => $u->getId(), $participants),
            ));
            sort($allIds, SORT_NUMERIC);
            $userRepo = $this->entityManager->getRepository(User::class);
            foreach ($allIds as $uid) {
                $u = $userRepo->find($uid);
                if ($u !== null) {
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
     * @param int $transactionId
     * @throws AccountBalanceBoundaryException
     * @throws TransactionBoundaryException
     * @throws TransactionInvalidException
     * @throws ParameterNotFoundException
     * @return Transaction
     */
    function revertTransaction(int $transactionId): Transaction {
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
     * @param Transaction $transaction
     * @throws AccountBalanceBoundaryException
     * @throws TransactionBoundaryException
     * @throws TransactionInvalidException
     * @throws ParameterNotFoundException
     */
    private function undoTransaction(Transaction $transaction) {
        $user = $transaction->getUser();

        // No checkTransactionBoundary() here: the original transaction was
        // already validated when created. Re-checking its amount against the
        // *current* (mutable) per-transaction limits could permanently block a
        // legitimate reversal if an operator later tightened those limits. The
        // account-balance ceiling below is still enforced post-reversal.
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
     * @param object $entity
     * @return void
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Doctrine\ORM\PessimisticLockException
     */
    private function lockAndRefresh(object $entity) {
        $this->entityManager->lock($entity, LockMode::PESSIMISTIC_WRITE);
        $this->entityManager->refresh($entity);
    }

    /**
     * @param int $amount
     * @throws TransactionBoundaryException
     * @throws TransactionInvalidException
     * @throws ParameterNotFoundException
     */
    private function checkTransactionBoundary($amount) {
        if (!$amount) {
            throw new TransactionInvalidException();
        }

        $upper = $this->settingsService->getOrDefault('payment.boundary.upper', false);
        if ($upper !== false && $amount > $upper) {
            throw new TransactionBoundaryException($amount, $upper);
        }

        $lower = $this->settingsService->getOrDefault('payment.boundary.lower', false);
        if ($lower !== false && $amount < $lower) {
            throw new TransactionBoundaryException($amount, $lower);
        }
    }

    /**
     * @param User $user
     * @throws AccountBalanceBoundaryException
     * @throws ParameterNotFoundException
     */
    private function checkAccountBalanceBoundary(User $user) {
        $balance = $user->getBalance();

        $upper = $this->settingsService->getOrDefault('account.boundary.upper', false);
        if ($upper !== false && $balance > $upper) {
            throw new AccountBalanceBoundaryException($user, $balance, $upper);
        }

        $lower = $this->settingsService->getOrDefault('account.boundary.lower', false);
        if ($lower !== false && $balance < $lower) {
            throw new AccountBalanceBoundaryException($user, $balance, $lower);
        }
    }
}
