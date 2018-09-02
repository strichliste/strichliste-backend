<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\AccountBalanceBoundaryException;
use App\Exception\TransactionBoundaryException;
use App\Exception\TransactionInvalidException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


class TransactionService {

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    function __construct(ContainerInterface $container, EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
        $this->settings = $container->getParameter('strichliste');
    }

    /**
     * @param User $user
     * @param int $amount
     * @param null|string $comment
     * @param Article|null $article
     * @param User|null $recipient
     * @throws AccountBalanceBoundaryException
     * @throws TransactionBoundaryException
     * @throws TransactionInvalidException
     * @return Transaction
     */
    function doTransaction(User $user, int $amount, string $comment = null, Article $article = null, User $recipient = null): Transaction {
        $transaction = new Transaction();

        $this->entityManager->transactional(function () use ($transaction, $user, $amount, $comment, $article, $recipient) {
            $transaction->setUser($user);
            $transaction->setComment($comment);

            if ($article) {
                $amount = $article->getAmount() * -1;
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
     * @param EntityManagerInterface $entityManager
     * @throws AccountBalanceBoundaryException
     * @throws TransactionBoundaryException
     * @throws TransactionInvalidException
     */
    private function undoTransaction(Transaction $transaction) {
        $recipientUser = $transaction->getUser();
        $this->checkTransactionBoundary($transaction->getAmount());

        $recipientUser->addBalance($transaction->getAmount() * -1);
        $this->checkAccountBalanceBoundary($recipientUser);

        $transaction->setDeleted(true);
        $this->entityManager->persist($transaction);
    }

    /**
     * @param int $amount
     * @throws TransactionBoundaryException
     * @throws TransactionInvalidException
     */
    private function checkTransactionBoundary($amount) {
        $upper = $this->settings['payment']['boundary']['upper'];
        $lower = $this->settings['payment']['boundary']['lower'];

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
     */
    private function checkAccountBalanceBoundary(User $user) {
        $balance = $user->getBalance();
        $upper = $this->settings['account']['boundary']['upper'];
        $lower = $this->settings['account']['boundary']['lower'];

        if ($balance > $upper) {
            throw new AccountBalanceBoundaryException($user, $balance, $upper);
        } elseif ($balance < $lower) {
            throw new AccountBalanceBoundaryException($user, $balance, $lower);
        }
    }
}