<?php

namespace App\Controller\Api;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\AccountBalanceBoundaryException;
use App\Exception\ArticleNotFoundException;
use App\Exception\TransactionBoundaryException;
use App\Exception\TransactionInvalidException;
use App\Exception\TransactionNotFoundException;
use App\Exception\UserNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api")
 */
class TransactionController extends AbstractController {

    /**
     * @Route("/transaction", methods="GET")
     */
    public function list(Request $request, EntityManagerInterface $entityManager) {
        $limit = $request->query->get('limit', 25);
        $offset = $request->query->get('offset');

        $count = $entityManager->getRepository(Transaction::class)->count([]);
        $transactions = $entityManager->getRepository(Transaction::class)->findAll($limit, $offset);

        return $this->json([
            'count' => $count,
            'transactions' => $transactions
        ]);
    }

    /**
     * @Route("/user/{userId}/transaction", methods="POST")
     * @throws UserNotFoundException
     * @throws TransactionBoundaryException
     * @throws ArticleNotFoundException
     * @throws AccountBalanceBoundaryException
     * @throws TransactionInvalidException
     */
    public function createUserTransactions($userId, Request $request, EntityManagerInterface $entityManager) {

        $user = $entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        $transaction = new Transaction();
        $transaction->setUser($user);

        $entityManager->transactional(function() use ($transaction, $user, $request, $entityManager) {
            $amount = (int) $request->request->get('amount', 0);

            $comment = $request->request->get('comment');
            $transaction->setComment($comment);

            $article = null;
            $articleId = $request->request->get('articleId');
            if ($articleId) {
                $article = $entityManager->getRepository(Article::class)->findOneActive($articleId);
                if (!$article) {
                    throw new ArticleNotFoundException($articleId);
                }

                $amount = $article->getAmount() * -1;
                $transaction->setArticle($article);

                $article->incrementUsageCount();
                $entityManager->persist($article);
            }

            $recipientId = $request->request->get('recipientId');
            if ($recipientId) {
                $recipientUser = $entityManager->getRepository(User::class)->find($recipientId);
                if (!$recipientUser) {
                    throw new UserNotFoundException($recipientId);
                }

                $recipientTransaction = new Transaction();
                $recipientTransaction->setAmount($amount * -1);
                $recipientTransaction->setArticle($article);
                $recipientTransaction->setComment($comment);
                $recipientTransaction->setUser($recipientUser);

                $recipientTransaction->setSenderTransaction($transaction);
                $transaction->setRecipientTransaction($recipientTransaction);

                $recipientUser->addBalance($amount * -1);
                $this->checkAccountBalanceBoundary($recipientUser);

                $entityManager->persist($recipientTransaction);
                $entityManager->persist($recipientUser);
            }

            $transaction->setAmount($amount);
            $this->checkTransactionBoundary($amount);

            $user->addBalance($amount);
            $this->checkAccountBalanceBoundary($user);

            $entityManager->persist($transaction);
            $entityManager->persist($user);
        });

        return $this->json([
            'transaction' => $transaction,
        ]);
    }

    /**
     * @Route("/user/{userId}/transaction", methods="GET")
     * @throws UserNotFoundException
     */
    public function getUserTransactions($userId, Request $request, EntityManagerInterface $entityManager) {
        $limit = $request->query->get('limit', 25);
        $offset = $request->query->get('offset');

        $user = $entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        $count = $entityManager->getRepository(Transaction::class)->countByUser($user);
        $transactions = $entityManager->getRepository(Transaction::class)->findByUser($user, $limit, $offset);

        return $this->json([
            'count' => $count,
            'transactions' => $transactions,
        ]);
    }

    /**
     * @Route("/user/{userId}/transaction/{transactionId}", methods="GET")
     * @throws UserNotFoundException
     * @throws TransactionNotFoundException
     */
    public function getUserTransaction($userId, $transactionId, EntityManagerInterface $entityManager) {
        $transaction = $this->getTransaction($userId, $transactionId, $entityManager);

        return $this->json([
            'transaction' => $transaction,
        ]);
    }

    /**
     * @Route("/user/{userId}/transaction/{transactionId}", methods="DELETE")
     * @throws UserNotFoundException
     * @throws TransactionNotFoundException
     */
    public function deleteTransaction($userId, $transactionId, EntityManagerInterface $entityManager) {
        $transaction = $this->getTransaction($userId, $transactionId, $entityManager);

        $entityManager->transactional(function() use ($entityManager, $transaction) {

            $article = $transaction->getArticle();
            if ($article) {
                $article->decrementUsageCount();
                $entityManager->persist($article);
            }

            $recipientTransaction = $transaction->getRecipientTransaction();
            if ($recipientTransaction) {
                $this->undoTransaction($recipientTransaction, $entityManager);
            }

            $senderTransaction = $transaction->getSenderTransaction();
            if ($senderTransaction) {
                $this->undoTransaction($senderTransaction, $entityManager);
            }

            $this->undoTransaction($transaction, $entityManager);
        });

        return $this->json([
            'transaction' => $transaction,
        ]);
    }

    /**
     * @param int $amount
     * @throws TransactionBoundaryException
     * @throws TransactionInvalidException
     */
    private function checkTransactionBoundary($amount) {
        $settings = $this->getParameter('strichliste');

        $upper = $settings['payment']['boundary']['upper'];
        $lower = $settings['payment']['boundary']['lower'];

        if ($amount > $upper) {
            throw new TransactionBoundaryException($amount, $upper);
        } else if ($amount < $lower){
            throw new TransactionBoundaryException($amount, $lower);
        } else if ($amount === 0) {
            throw new TransactionInvalidException();
        }
    }

    /**
     * @param User $user
     * @throws AccountBalanceBoundaryException
     */
    private function checkAccountBalanceBoundary(User $user) {
        $settings = $this->getParameter('strichliste');

        $balance = $user->getBalance();
        $upper = $settings['account']['boundary']['upper'];
        $lower = $settings['account']['boundary']['lower'];

        if ($balance > $upper) {
            throw new AccountBalanceBoundaryException($user, $balance, $upper);
        } else if ($balance < $lower){
            throw new AccountBalanceBoundaryException($user, $balance, $lower);
        }
    }

    /**
     * @param int $userId
     * @param int $transactionId
     * @param EntityManagerInterface $entityManager
     * @return Transaction
     * @throws TransactionNotFoundException
     * @throws UserNotFoundException
     */
    private function getTransaction(int $userId, int $transactionId, EntityManagerInterface $entityManager): Transaction {
        $user = $entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        $transaction = $entityManager->getRepository(Transaction::class)->findByUserAndId($user, $transactionId);
        if (!$transaction) {
            throw new TransactionNotFoundException($user, $transactionId);
        }

        return $transaction;
    }

    /**
     * @param Transaction $transaction
     * @param EntityManagerInterface $entityManager
     */
    private function undoTransaction(Transaction $transaction, EntityManagerInterface $entityManager) {
        $recipientUser = $transaction->getUser();
        $recipientUser->setBalance($recipientUser->getBalance() + ($transaction->getAmount() * -1));

        $transaction->setDeleted(true);
        $entityManager->persist($transaction);
    }
}
