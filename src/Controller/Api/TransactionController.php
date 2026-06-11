<?php

namespace App\Controller\Api;

use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\ParameterInvalidException;
use App\Exception\TransactionNotFoundException;
use App\Exception\UserNotFoundException;
use App\Repository\TransactionRepository;
use App\Serializer\TransactionSerializer;
use App\Service\TransactionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class TransactionController extends AbstractController
{
    public function __construct(private readonly TransactionSerializer $transactionSerializer)
    {
    }

    #[Route('/transaction', methods: ['GET'])]
    public function list(Request $request, TransactionRepository $transactionRepository): JsonResponse
    {
        $limit = (int) $request->query->get('limit', 25);
        $offset = $request->query->get('offset');

        $count = $transactionRepository->count([]);
        $transactions = $transactionRepository->findAllPaginated($limit, null === $offset ? null : (int) $offset);

        return $this->json([
            'count' => $count,
            'transactions' => array_map(fn (Transaction $transaction) => $this->transactionSerializer->serialize($transaction), $transactions),
        ]);
    }

    #[Route('/user/{userId}/transaction', methods: ['POST'])]
    public function createUserTransactions(string $userId, Request $request, TransactionService $transactionService, EntityManagerInterface $entityManager): JsonResponse
    {
        $amount = $request->request->get('amount');
        $quantity = $request->request->get('quantity');
        $comment = $request->request->get('comment');
        $recipientId = $request->request->get('recipientId');
        $articleId = $request->request->get('articleId');

        if (mb_strlen($comment ?? '') > 255) {
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

    #[Route('/user/{userId}/transaction', methods: ['GET'])]
    public function getUserTransactions(string $userId, Request $request, EntityManagerInterface $entityManager, TransactionRepository $transactionRepository): JsonResponse
    {
        $limit = (int) $request->query->get('limit', 25);
        $offset = $request->query->get('offset');

        $user = $entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        $count = $transactionRepository->countByUser($user);
        $transactions = $transactionRepository->findByUser($user, $limit, null === $offset ? null : (int) $offset);

        return $this->json([
            'count' => $count,
            'transactions' => array_map(fn (Transaction $transaction) => $this->transactionSerializer->serialize($transaction), $transactions),
        ]);
    }

    #[Route('/user/{userId}/transaction/{transactionId}', methods: ['GET'])]
    public function getUserTransaction(string $userId, string $transactionId, EntityManagerInterface $entityManager): JsonResponse
    {
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

    #[Route('/user/{userId}/transaction/{transactionId}', methods: ['DELETE'])]
    public function deleteTransaction(string $userId, string $transactionId, TransactionService $transactionService): JsonResponse
    {
        $transaction = $transactionService->revertTransaction((int) $transactionId);

        return $this->json([
            'transaction' => $this->transactionSerializer->serialize($transaction),
        ]);
    }
}
