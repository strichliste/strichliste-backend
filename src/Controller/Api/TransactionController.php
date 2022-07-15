<?php

namespace App\Controller\Api;

use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\ParameterInvalidException;
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
    function list(Request $request, EntityManagerInterface $entityManager) {
        $limit = $request->query->get('limit', 25);
        $offset = $request->query->get('offset');

        $count = $entityManager->getRepository(Transaction::class)->count([]);
        $transactions = $entityManager->getRepository(Transaction::class)->findAll($limit, $offset);

        return $this->json([
            'count' => $count,
            'transactions' => array_map(function (Transaction $transaction) {
                return $this->transactionSerializer->serialize($transaction);
            }, $transactions)
        ]);
    }

    /**
     * @Route("/user/{userId}/transaction", methods="POST")
     */
    function createUserTransactions($userId, Request $request, TransactionService $transactionService, EntityManagerInterface $entityManager) {
        $amount = $request->request->get('amount');
        $quantity = $request->request->get('quantity');
        $comment = $request->request->get('comment');
        $recipientId = $request->request->get('recipientId');
        $articleId = $request->request->get('articleId');

        if (mb_strlen($comment) > 255) {
            throw new ParameterInvalidException('comment');
        }

        $user = $entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        $transaction = $transactionService->doTransaction($user, $amount, $comment, $quantity, $articleId, $recipientId);

        return $this->json([
            'transaction' => $this->transactionSerializer->serialize($transaction),
        ]);
    }

    /**
     * @Route("/user/{userId}/transaction", methods="GET")
     */
    function getUserTransactions($userId, Request $request, EntityManagerInterface $entityManager) {
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
            'transactions' => array_map(function (Transaction $transaction) {
                return $this->transactionSerializer->serialize($transaction);
            }, $transactions),
        ]);
    }

    /**
     * @Route("/user/{userId}/transaction/{transactionId}", methods="GET")
     */
    function getUserTransaction($userId, $transactionId, EntityManagerInterface $entityManager) {
        $user = $entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        $transaction = $entityManager->getRepository(Transaction::class)->find($transactionId);
        if (!$transaction) {
            throw new TransactionNotFoundException($transactionId);
        }

        return $this->json([
            'transaction' => $this->transactionSerializer->serialize($transaction),
        ]);
    }

    /**
     * @Route("/user/{userId}/transaction/{transactionId}", methods="DELETE")
     */
    function deleteTransaction($userId, $transactionId, TransactionService $transactionService) {
        $transaction = $transactionService->revertTransaction($transactionId);

        return $this->json([
            'transaction' => $this->transactionSerializer->serialize($transaction),
        ]);
    }
}
