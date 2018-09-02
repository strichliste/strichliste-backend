<?php

namespace App\Controller\Api;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\AccountBalanceBoundaryException;
use App\Exception\ArticleInactiveException;
use App\Exception\ArticleNotFoundException;
use App\Exception\ParameterNotFoundException;
use App\Exception\TransactionBoundaryException;
use App\Exception\TransactionInvalidException;
use App\Exception\TransactionNotFoundException;
use App\Exception\UserNotFoundException;
use App\Serializer\TransactionSerializer;
use App\Service\TransactionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api")
 */
class TransactionController extends AbstractController {

    /**
     * @var TransactionSerializer
     */
    private $transactionSerializer;

    function __construct(TransactionSerializer $transactionSerializer) {
        $this->transactionSerializer = $transactionSerializer;
    }

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
            'transactions' => array_map(function(Transaction $transaction) {
                return $this->transactionSerializer->serialize($transaction);
            }, $transactions)
        ]);
    }

    /**
     * @Route("/user/{userId}/transaction", methods="POST")
     * @throws UserNotFoundException
     * @throws TransactionBoundaryException
     * @throws ArticleNotFoundException
     * @throws ArticleInactiveException
     * @throws AccountBalanceBoundaryException
     * @throws TransactionInvalidException
     * @throws ParameterNotFoundException
     */
    public function createUserTransactions($userId, Request $request, TransactionService $transactionService, EntityManagerInterface $entityManager) {

        $amount = (int)$request->request->get('amount');
        $comment = $request->request->get('comment');

        $user = $entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        $article = null;
        $articleId = $request->request->get('articleId');
        if ($articleId) {
            $article = $entityManager->getRepository(Article::class)->find($articleId);
            if (!$article) {
                throw new ArticleNotFoundException($articleId);
            }

            if (!$article->isActive()) {
                throw new ArticleInactiveException($article);
            }
        }

        $recipient = null;
        $recipientId = $request->request->get('recipientId');
        if ($recipientId) {
            $recipient = $entityManager->getRepository(User::class)->find($recipientId);
            if (!$recipient) {
                throw new UserNotFoundException($recipientId);
            }
        }

        $transaction = $transactionService->doTransaction($user, $amount, $comment, $article, $recipient);

        return $this->json([
            'transaction' => $this->transactionSerializer->serialize($transaction),
        ]);
    }

    /**
     * @Route("/user/{userId}/transaction", methods="GET")
     * @throws UserNotFoundException
     */
    public function getUserTransactions($userId, Request $request, TransactionSerializer $transactionSerializer, EntityManagerInterface $entityManager) {
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
            'transactions' => array_map(function(Transaction $transaction) {
                return $this->transactionSerializer->serialize($transaction);
            }, $transactions),
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
            'transaction' => $this->transactionSerializer->serialize($transaction),
        ]);
    }

    /**
     * @Route("/user/{userId}/transaction/{transactionId}", methods="DELETE")
     * @throws TransactionNotFoundException
     * @throws UserNotFoundException
     * @throws AccountBalanceBoundaryException
     * @throws TransactionBoundaryException
     * @throws TransactionInvalidException
     * @throws ParameterNotFoundException
     */
    public function deleteTransaction($userId, $transactionId, TransactionService $transactionService, EntityManagerInterface $entityManager) {
        $transaction = $this->getTransaction($userId, $transactionId, $entityManager);
        $transactionService->revertTransaction($transaction);

        return $this->json([
            'transaction' => $this->transactionSerializer->serialize($transaction),
        ]);
    }

    /**
     * @param int $userId
     * @param int $transactionId
     * @param EntityManagerInterface $entityManager
     * @throws TransactionNotFoundException
     * @throws UserNotFoundException
     * @return Transaction
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
}
