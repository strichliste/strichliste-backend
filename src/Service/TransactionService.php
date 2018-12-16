<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\AccountBalanceBoundaryException;
use App\Exception\ParameterNotFoundException;
use App\Exception\TransactionBoundaryException;
use App\Exception\TransactionInvalidException;
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
     * @param Article|null $article
     * @param User|null $recipient
     * @throws AccountBalanceBoundaryException
     * @throws TransactionBoundaryException
     * @throws TransactionInvalidException
     * @throws ParameterNotFoundException
     * @return Transaction
     */
    function doTransaction(User $user, ?int $amount, string $comment = null, ?int $quantity = 1, Article $article = null, User $recipient = null): Transaction {
        $transaction = new Transaction();

        if (($recipient || $article) && $amount < 0) {
            throw new TransactionInvalidException('Amount can\'t be negative when sending money or buying an article');
        }

        $this->entityManager->transactional(function () use ($transaction, $user, $amount, $comment, $quantity, $article, $recipient) {
            $transaction->setUser($user);
            $transaction->setComment($comment);

            if ($article) {
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

            $transaction->setAmount($amount);
            $this->checkTransactionBoundary($amount);

            $user->addBalance($amount);
            $this->checkAccountBalanceBoundary($user);

            $this->entityManager->persist($transaction);
            $this->entityManager->persist($user);
        });

        return $transaction;
    }

    /**
     * @param Transaction $transaction
     * @throws AccountBalanceBoundaryException
     * @throws TransactionBoundaryException
     * @throws TransactionInvalidException
     * @throws ParameterNotFoundException
     * @return Transaction
     */
    function revertTransaction(Transaction $transaction): Transaction {
        $this->entityManager->transactional(function () use ($transaction) {

            $article = $transaction->getArticle();
            if ($article) {
                $article->decrementUsageCount();
                $this->entityManager->persist($article);
            }

            $recipientTransaction = $transaction->getRecipientTransaction();
            if ($recipientTransaction) {
                $this->undoTransaction($recipientTransaction);
            }

            $senderTransaction = $transaction->getSenderTransaction();
            if ($senderTransaction) {
                $this->undoTransaction($senderTransaction);
            }

            $this->undoTransaction($transaction);
        });

        return $transaction;
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
        $upper = $this->settingsService->get('payment.boundary.upper');
        $lower = $this->settingsService->get('payment.boundary.lower');

        if ($amount > $upper) {
            throw new TransactionBoundaryException($amount, $upper);
        } elseif ($amount < $lower) {
            throw new TransactionBoundaryException($amount, $lower);
        } elseif (!$amount) {
            throw new TransactionInvalidException();
        }
    }

    /**
     * @param User $user
     * @throws AccountBalanceBoundaryException
     * @throws ParameterNotFoundException
     */
    private function checkAccountBalanceBoundary(User $user) {
        $balance = $user->getBalance();
        $upper = $this->settingsService->get('account.boundary.upper');
        $lower = $this->settingsService->get('account.boundary.lower');

        if ($balance > $upper) {
            throw new AccountBalanceBoundaryException($user, $balance, $upper);
        } elseif ($balance < $lower) {
            throw new AccountBalanceBoundaryException($user, $balance, $lower);
        }
    }
}