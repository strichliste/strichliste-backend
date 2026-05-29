<?php

namespace App\Controller\Ui;

use App\Repository\UserRepository;
use App\Service\MetricsService;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class MetricsController extends AbstractController {

    public function __construct(
        private MetricsService $metrics,
        private UserRepository $userRepository,
        private SettingsService $settings,
    ) {
    }

    #[Route('/metrics', name: 'metrics_global', methods: ['GET'])]
    public function global(): Response {
        return $this->render('metrics/global.html.twig', [
            'balance' => $this->metrics->totalBalance(),
            'transactionCount' => $this->metrics->totalTransactionCount(),
            'userCount' => $this->metrics->totalUserCount(),
            'days' => $this->metrics->transactionsPerDay(30),
            'currencySymbol' => $this->settings->getOrDefault('i18n.currency.symbol', '€'),
        ]);
    }

    #[Route('/user/{id}/metrics', name: 'metrics_user', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function user(int $id): Response {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new NotFoundHttpException();
        }

        return $this->render('metrics/user.html.twig', [
            'user' => $user,
            'articles' => $this->metrics->userArticles($user, 10),
            'txCount' => $this->metrics->userTransactionCount($user),
            'outgoing' => $this->metrics->userOutgoing($user),
            'incoming' => $this->metrics->userIncoming($user),
            'currencySymbol' => $this->settings->getOrDefault('i18n.currency.symbol', '€'),
        ]);
    }
}
