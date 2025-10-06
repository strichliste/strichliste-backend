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
    function doTransaction(User $user, ?int $amount, string $comment = null, ?int $quantity = 1, ?int $articleId = null, ?int $recipientId = null): Transaction {

        if (($recipientId || $articleId) && $amount > 0) {
            throw new TransactionInvalidException('Amount can\'t be positive when sending money or buying an article');
        }

        $senderId = $user->getId();
        return $this->entityManager->transactional(function () use ($senderId, $amount, $comment, $quantity, $articleId, $recipientId) {
            $transaction = new Transaction();
            $transaction->setComment($comment);

            $sender = null;
            $recipient = null;
            if ($recipientId) {
                if ($senderId < $recipientId) {
                    $sender = $this->entityManager->getRepository(User::class)->find($senderId, LockMode::PESSIMISTIC_WRITE);
                    $recipient = $this->entityManager->getRepository(User::class)->find($recipientId, LockMode::PESSIMISTIC_WRITE);
                } else {
                    $recipient = $this->entityManager->getRepository(User::class)->find($recipientId, LockMode::PESSIMISTIC_WRITE);
                    $sender = $this->entityManager->getRepository(User::class)->find($senderId, LockMode::PESSIMISTIC_WRITE);
                }

                if (!$recipient) {
                    throw new UserNotFoundException($recipientId);
                }
            } else {
                $sender = $this->entityManager->getRepository(User::class)->find($senderId, LockMode::PESSIMISTIC_WRITE);
            }

            if (!$sender) {
                throw new UserNotFoundException($senderId);
            }

            $article = null;
            if ($articleId) {
                $article = $this->entityManager->getRepository(Article::class)->find($articleId, LockMode::PESSIMISTIC_WRITE);
                if (!$article) {
                    throw new ArticleNotFoundException($articleId);
                }

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

            if ($recipient) {
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
        return $this->entityManager->transactional(function () use ($transactionId) {
            $transaction = $this->entityManager->getRepository(Transaction::class)->find($transactionId);
            if (!$transaction) {
                throw new TransactionNotFoundException($transactionId);
            }

            $transactionIds = [$initialTransaction->getId()];
            $userIds = [$initialTransaction->getUser()->getId()];
            $pairedTransaction = $initialTransaction->getRecipientTransaction() ?? $initialTransaction->getSenderTransaction();
            if ($pairedTransaction) {
                $transactionIds[] = $pairedTransaction->getId();
                $userIds[] = $pairedTransaction->getUser()->getId();
            }

            $article = $initialTransaction->getArticle();

            sort($transactionIds, SORT_NUMERIC);
            sort($userIds, SORT_NUMERIC);


            $transactions = [];
            foreach ($transactionIds as $id) {
                $trans = $this->entityManager->getRepository(Transaction::class)->find($id, LockMode::PESSIMISTIC_WRITE);
                if (!$trans) {
                    throw new TransactionNotFoundException($id);
                }

                $transactions[] = $trans;
            }

            foreach ($useIds as $id) {
                $user = $this->entityManager->getRepository(User::class)->find($id, LockMode::PESSIMISTIC_WRITE);
                if (!$user) {
                    throw new UserNotFoundException($id);
                }
            }

            if ($article) {
                $article = $this->entityManager->getRepository(Article::class)->find($article->getId(), LockMode::PESSIMISTIC_WRITE);
                if (!$article) {
                    throw new ArticleNotFoundException($article->getId());
                }
            }

            $this->entityManager->refresh($transactionForReversal);

            if ($transaction->isDeleted()) {
                throw new TransactionNotDeletableException($transaction);
            }

            if ($article) {
                $article->decrementUsageCount();
                $this->entityManager->persist($article);
            }

            foreach ($transactions as $trans) {
                $this->undoTransaction($trans);
            }

            return $transaction;
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
