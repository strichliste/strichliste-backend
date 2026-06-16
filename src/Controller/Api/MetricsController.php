<?php

namespace App\Controller\Api;

use App\Dto\Api\Article as ArticleSchema;
use App\Dto\Api\MetricsDay as MetricsDaySchema;
use App\Entity\Article;
use App\Exception\UserNotFoundException;
use App\Repository\ArticleRepository;
use App\Repository\UserRepository;
use App\Serializer\ArticleSerializer;
use App\Service\MetricsService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\Serialize;
use Symfony\Component\Routing\Attribute\Route;

class MetricsController extends AbstractController
{
    public function __construct(private readonly MetricsService $metrics)
    {
    }

    /**
     * Returns an array (not a typed Response DTO): the per-day series carries the
     * legacy quirk where `charged`/`spent` are either the int 0 or a {amount,
     * transactions} object, plus other ad-hoc nested shapes that a DTO can't model
     * cleanly. #[Serialize] still removes the manual JsonResponse plumbing.
     *
     * @return array<string, mixed>
     */
    #[Route('/api/metrics', methods: ['GET'])]
    #[OA\Get(
        summary: 'Global metrics',
        tags: ['metrics'],
        parameters: [
            new OA\Parameter(name: 'days', in: 'query', required: false, description: 'Length of the per-day series (clamped to 1–3650).', schema: new OA\Schema(type: 'integer', default: 30)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Totals, the active articles by usage, and a per-day series (newest first).', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'balance', type: 'integer'),
                new OA\Property(property: 'transactionCount', type: 'integer'),
                new OA\Property(property: 'userCount', type: 'integer'),
                new OA\Property(property: 'articles', type: 'array', items: new OA\Items(ref: new Model(type: ArticleSchema::class))),
                new OA\Property(property: 'days', type: 'array', items: new OA\Items(ref: new Model(type: MetricsDaySchema::class))),
            ])),
        ],
    )]
    #[Serialize]
    public function metrics(Request $request, ArticleRepository $articleRepository, ArticleSerializer $articleSerializer): array
    {
        // clamp: huge values allocate an array row per day, negative ones make DateTime throw
        $days = max(1, min(3650, $request->query->getInt('days', 30)));
        $articles = $articleRepository->findBy(['active' => true], ['usageCount' => 'DESC']);

        return [
            'balance' => $this->metrics->totalBalance(),
            'transactionCount' => $this->metrics->totalTransactionCount(),
            'userCount' => $this->metrics->totalUserCount(),
            'articles' => array_map(
                fn (Article $article) => $articleSerializer->serialize($article, 0),
                $articles
            ),
            'days' => $this->metrics->transactionsPerDay($days, 'api'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[Route('/api/user/{userId}/metrics', methods: ['GET'])]
    #[OA\Get(
        summary: 'Per-user metrics',
        tags: ['metrics'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, description: 'User id, or the exact user name.', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Balance, article purchase breakdown (reverted purchases included — legacy behavior) and transfer counters.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'balance', type: 'integer'),
                new OA\Property(property: 'articles', type: 'array', items: new OA\Items(type: 'object', properties: [
                    new OA\Property(property: 'article', ref: new Model(type: ArticleSchema::class)),
                    new OA\Property(property: 'count', type: 'integer'),
                    new OA\Property(property: 'amount', type: 'integer'),
                ])),
                new OA\Property(property: 'transactions', type: 'object', properties: [
                    new OA\Property(property: 'count', type: 'integer'),
                    new OA\Property(property: 'outgoing', type: 'object', properties: [
                        new OA\Property(property: 'count', type: 'integer'),
                        new OA\Property(property: 'amount', type: 'integer'),
                    ]),
                    new OA\Property(property: 'incoming', type: 'object', properties: [
                        new OA\Property(property: 'count', type: 'integer'),
                        new OA\Property(property: 'amount', type: 'integer'),
                    ]),
                ]),
            ])),
            new OA\Response(response: 404, ref: '#/components/responses/Error'),
        ],
    )]
    #[Serialize]
    public function userMetrics(string $userId, ArticleSerializer $articleSerializer, UserRepository $userRepository): array
    {
        $user = $userRepository->findByIdentifier($userId);
        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        $articles = $this->metrics->userArticles($user, PHP_INT_MAX, includeDeleted: true); // legacy API: all rows, reverted purchases included
        $outgoing = $this->metrics->userOutgoing($user);
        $incoming = $this->metrics->userIncoming($user);

        return [
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
        ];
    }
}
