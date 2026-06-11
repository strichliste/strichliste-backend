<?php

namespace App\Controller\Ui;

use App\Entity\User;
use App\Service\MetricsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MetricsController extends AbstractController {

    public function __construct(
        private MetricsService $metrics,
    ) {
    }

    #[Route('/metrics', name: 'metrics_global', methods: ['GET'])]
    public function global(): Response {
        return $this->render('metrics/global.html.twig', [
            'balance' => $this->metrics->totalBalance(),
            'transactionCount' => $this->metrics->totalTransactionCount(),
            'userCount' => $this->metrics->totalUserCount(),
            'days' => $this->metrics->transactionsPerDay(30),
            'topArticles' => $this->metrics->topArticles(30),
        ]);
    }

    #[Route('/user/{id}/metrics', name: 'metrics_user', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function user(User $user): Response {
        return $this->render('metrics/user.html.twig', [
            'user' => $user,
            'articles' => $this->metrics->userArticles($user, 10),
            'txCount' => $this->metrics->userTransactionCount($user),
            'outgoing' => $this->metrics->userOutgoing($user),
            'incoming' => $this->metrics->userIncoming($user),
        ]);
    }
}
