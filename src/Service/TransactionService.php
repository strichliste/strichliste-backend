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

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var SettingsService
     */
    private $settingsService;

    function __construct(SettingsService $settingsService, EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
        $this->settingsService = $settingsService;

        $connection = $entityManager->getConnection();
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
        $this->checkTransactionBoundary($transaction->getAmount());

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
