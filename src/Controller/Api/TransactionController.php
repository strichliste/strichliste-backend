<?php

namespace App\Controller\Api;

use App\ApiDoc\Transaction as TransactionSchema;
use App\Dto\Api\CreateTransactionDto;
use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\TransactionNotDeletableException;
use App\Exception\TransactionNotFoundException;
use App\Exception\UserNotFoundException;
use App\Repository\TransactionRepository;
use App\Serializer\TransactionSerializer;
use App\Service\TransactionService;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class TransactionController extends AbstractController
{
    public function __construct(private readonly TransactionSerializer $transactionSerializer)
    {
    }

    #[Route('/transaction', methods: ['GET'])]
    #[OA\Get(
        summary: 'List all transactions (oldest first)',
        tags: ['transaction'],
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25)),
            new OA\Parameter(name: 'offset', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Transactions with total count.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'count', type: 'integer'),
                new OA\Property(property: 'transactions', type: 'array', items: new OA\Items(ref: new Model(type: TransactionSchema::class))),
            ])),
        ],
    )]
    public function list(Request $request, TransactionRepository $transactionRepository): JsonResponse
    {
        $limit = $request->query->getInt('limit', 25);
        $offset = $request->query->has('offset') ? $request->query->getInt('offset') : null;

        $count = $transactionRepository->count([]);
        $transactions = $transactionRepository->findAllPaginated($limit, $offset);

        return $this->json([
            'count' => $count,
            'transactions' => array_map($this->transactionSerializer->serialize(...), $transactions),
        ]);
    }

    #[Route('/user/{userId}/transaction', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create a transaction',
        description: 'Deposit/dispense (`amount`), article purchase (`articleId`, optional `quantity`) or transfer (`amount` + `recipientId`). Amounts are signed cents.',
        tags: ['transaction'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, description: 'User id, or the exact user name.', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: [
            new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(ref: new Model(type: CreateTransactionDto::class))),
            new OA\MediaType(mediaType: 'application/x-www-form-urlencoded', schema: new OA\Schema(ref: new Model(type: CreateTransactionDto::class))),
        ]),
        responses: [
            new OA\Response(response: 200, description: 'The created transaction.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'transaction', ref: new Model(type: TransactionSchema::class)),
            ])),
            new OA\Response(response: 422, ref: '#/components/responses/Error'),
            new OA\Response(response: 404, ref: '#/components/responses/Error'),
        ],
    )]
    public function createUserTransactions(string $userId, #[MapRequestPayload] CreateTransactionDto $dto, TransactionService $transactionService, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        $transaction = $transactionService->doTransaction($user, $dto->amount, $dto->comment, $dto->quantity, $dto->articleId, $dto->recipientId);

        return $this->json([
            'transaction' => $this->transactionSerializer->serialize($transaction),
        ]);
    }

    #[Route('/user/{userId}/transaction', methods: ['GET'])]
    #[OA\Get(
        summary: 'List a user\'s transactions (newest first)',
        tags: ['transaction'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, description: 'User id, or the exact user name.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25)),
            new OA\Parameter(name: 'offset', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Transactions with total count.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'count', type: 'integer'),
                new OA\Property(property: 'transactions', type: 'array', items: new OA\Items(ref: new Model(type: TransactionSchema::class))),
            ])),
            new OA\Response(response: 404, ref: '#/components/responses/Error'),
        ],
    )]
    public function getUserTransactions(string $userId, Request $request, EntityManagerInterface $entityManager, TransactionRepository $transactionRepository): JsonResponse
    {
        $limit = $request->query->getInt('limit', 25);
        $offset = $request->query->has('offset') ? $request->query->getInt('offset') : null;

        $user = $entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        $count = $transactionRepository->countByUser($user);
        $transactions = $transactionRepository->findByUser($user, $limit, $offset);

        return $this->json([
            'count' => $count,
            'transactions' => array_map($this->transactionSerializer->serialize(...), $transactions),
        ]);
    }

    #[Route('/user/{userId}/transaction/{transactionId}', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a single transaction',
        tags: ['transaction'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, description: 'User id, or the exact user name.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'transactionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The transaction.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'transaction', ref: new Model(type: TransactionSchema::class)),
            ])),
            new OA\Response(response: 404, ref: '#/components/responses/Error'),
        ],
    )]
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
    #[OA\Delete(
        summary: 'Revert (undo) a transaction',
        tags: ['transaction'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, description: 'User id, or the exact user name.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'transactionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The reverted transaction (isDeleted=true).', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'transaction', ref: new Model(type: TransactionSchema::class)),
            ])),
            new OA\Response(response: 400, ref: '#/components/responses/Error'),
            new OA\Response(response: 404, ref: '#/components/responses/Error'),
        ],
    )]
    public function deleteTransaction(string $userId, string $transactionId, TransactionService $transactionService, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        // the {userId} segment alone authorizes nothing — the transaction must belong to this
        // user, otherwise any client could revert any transaction by id (mirrors the UI undo
        // guard in TransactionWriteController). A mismatch is reported as not-found, not 403,
        // to stay inside the frozen error envelope and not disclose other users' transactions.
        $transaction = $entityManager->getRepository(Transaction::class)->find($transactionId);
        if (!$transaction || $transaction->getUser()->getId() !== $user->getId()) {
            throw new TransactionNotFoundException($transactionId);
        }

        // honor the configured undo policy (payment.undo.enabled / timeout) on the API path too,
        // mirroring the UI undo guard — otherwise a client can revert transactions the policy
        // means to be final (undo disabled, or older than the undo window).
        if (!$transactionService->isDeletable($transaction)) {
            throw new TransactionNotDeletableException($transaction);
        }

        $reverted = $transactionService->revertTransaction((int) $transactionId);

        return $this->json([
            'transaction' => $this->transactionSerializer->serialize($reverted),
        ]);
    }
}
