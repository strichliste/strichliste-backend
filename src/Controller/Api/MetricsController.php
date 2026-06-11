<?php

namespace App\Controller\Api;

use App\Entity\Article;
use App\Exception\UserNotFoundException;
use App\Repository\ArticleRepository;
use App\Repository\UserRepository;
use App\Serializer\ArticleSerializer;
use App\Service\MetricsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class MetricsController extends AbstractController
{
    public function __construct(private MetricsService $metrics)
    {
    }

    #[Route('/api/metrics', methods: ['GET'])]
    public function metrics(Request $request, ArticleRepository $articleRepository, ArticleSerializer $articleSerializer): JsonResponse
    {
        // clamp: huge values allocate an array row per day, negative ones make DateTime throw
        $days = max(1, min(3650, (int) $request->query->get('days', 30)));
        $articles = $articleRepository->findBy(['active' => true], ['usageCount' => 'DESC']);

        return $this->json([
            'balance' => $this->metrics->totalBalance(),
            'transactionCount' => $this->metrics->totalTransactionCount(),
            'userCount' => $this->metrics->totalUserCount(),
            'articles' => array_map(
                fn (Article $article) => $articleSerializer->serialize($article, 0),
                $articles
            ),
            'days' => $this->metrics->transactionsPerDay($days, 'api'),
        ]);
    }

    #[Route('/api/user/{userId}/metrics', methods: ['GET'])]
    public function userMetrics(string $userId, ArticleSerializer $articleSerializer, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->findByIdentifier($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        $articles = $this->metrics->userArticles($user, PHP_INT_MAX, includeDeleted: true); // legacy API: all rows, reverted purchases included
        $outgoing = $this->metrics->userOutgoing($user);
        $incoming = $this->metrics->userIncoming($user);

        return $this->json([
            'balance' => $user->getBalance(),
            'articles' => array_map(
                fn (array $row) => [
                    'article' => $articleSerializer->serialize($row['article'], 0),
                    'count' => (int) $row['cnt'],
                    'amount' => (int) $row['amt'],
                ],
                $articles
            ),
            'transactions' => [
                'count' => $this->metrics->userTransactionCount($user),
                'outgoing' => ['count' => $outgoing['cnt'], 'amount' => $outgoing['amount']],
                'incoming' => ['count' => $incoming['cnt'], 'amount' => $incoming['amount']],
            ],
        ]);
    }
}
