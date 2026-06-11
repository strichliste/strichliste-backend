<?php

namespace App\Controller\Ui;

use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Service\TransactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TransactionHistoryController extends AbstractController
{
    private const PAGE_SIZE = 15;

    public function __construct(
        private TransactionRepository $transactionRepository,
        private TransactionService $transactionService,
    ) {
    }

    #[Route('/user/{id}/transactions', name: 'users_transactions', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function index(User $user, Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $total = $this->transactionRepository->countByUser($user);
        $totalPages = $total > 0 ? (int) ceil($total / self::PAGE_SIZE) : 1;
        $page = min($page, $totalPages);
        $offset = ($page - 1) * self::PAGE_SIZE;

        $transactions = $this->transactionRepository->findByUser($user, self::PAGE_SIZE, $offset);
        $rows = array_map(fn ($tx) => [
            'tx' => $tx,
            'deletable' => $this->transactionService->isDeletable($tx),
        ], $transactions);

        return $this->render('transactions/history.html.twig', [
            'user' => $user,
            'rows' => $rows,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }
}
