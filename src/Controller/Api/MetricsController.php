<?php

namespace App\Controller\Api;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\UserNotFoundException;
use App\Repository\ArticleRepository;
use App\Serializer\ArticleSerializer;
use DateInterval;
use DatePeriod;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class MetricsController extends AbstractController {
    #[Route('/api/metrics', methods: ['GET'])]
    public function metrics(Request $request, ArticleRepository $articleRepository, ArticleSerializer $articleSerializer, EntityManagerInterface $entityManager): JsonResponse {
        $days = $request->query->get('days', 30);
        $articles = $articleRepository->findBy(['active' => true], ['usageCount' => 'DESC']);

        return $this->json([
            'balance' => $this->getBalance($entityManager),
            'transactionCount' => $this->getTransactionCount($entityManager),
            'userCount' => $this->getUserCount($entityManager),
            'articles' => array_map(static fn (Article $article): array => $articleSerializer->serialize($article, 0), $articles),
            'days' => $this->getTransactionsPerDay($entityManager, $days),
        ]);
    }

    #[Route('/api/user/{userId}/metrics', methods: ['GET'])]
    public function userMetrics($userId, ArticleSerializer $articleSerializer, EntityManagerInterface $entityManager): JsonResponse {
        /**
         * @var User $user
         */
        $user = $entityManager->getRepository(User::class)->findByIdentifier($userId);

        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        $articles = $entityManager->createQueryBuilder()
            ->select('COUNT(a.id) as count, SUM(t.amount) * -1 as amount, a as article')
            ->from(Transaction::class, 't')
            ->innerJoin(Article::class, 'a', Join::WITH, 'a = t.article')
            ->where('t.user = :user')
            ->andWhere('t.article IS NOT NULL')
            ->setParameter('user', $user)
            ->groupBy('a')
            ->orderBy('COUNT(a)', 'DESC')
            ->getQuery()
            ->getResult();

        $transactionCount = $entityManager->createQueryBuilder()
            ->select('COUNT(t.id) as count')
            ->from(Transaction::class, 't')
            ->where('t.user = :user')
            ->andWhere('t.deleted = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $outgoingTransactions = $this->getUserTransactionBaseQuery($user, $entityManager)
            ->andWhere('t.recipientTransaction IS NOT NULL')
            ->getQuery()
            ->getOneOrNullResult(Query::HYDRATE_ARRAY);

        $incomingTransactions = $this->getUserTransactionBaseQuery($user, $entityManager)
            ->andWhere('t.senderTransaction IS NOT NULL')
            ->getQuery()
            ->getOneOrNullResult(Query::HYDRATE_ARRAY);

        return $this->json([
            'balance' => $user->getBalance(),

            'articles' => array_map(static fn ($article): array => [
                'article' => $articleSerializer->serialize($article['article'], 0),
                'count' => (int) $article['count'],
                'amount' => (int) $article['amount'],
            ], $articles),

            'transactions' => [
                'count' => (int) $transactionCount,
                'outgoing' => ['count' => (int) $outgoingTransactions['count'], 'amount' => (int) $outgoingTransactions['amount']],
                'incoming' => ['count' => (int) $incomingTransactions['count'], 'amount' => (int) $incomingTransactions['amount']],
            ],
        ]);
    }

    private function getBalance(EntityManagerInterface $entityManager): int {
        return $entityManager->createQueryBuilder()
            ->select('SUM(u.balance) as balance')
            ->from(User::class, 'u')
            ->where('u.disabled = false')
            ->getQuery()
            ->getSingleScalarResult() ?: 0;
    }

    private function getTransactionCount(EntityManagerInterface $entityManager): int {
        return $entityManager->createQueryBuilder()
            ->select('COUNT(t) as count')
            ->from(Transaction::class, 't')
            ->getQuery()
            ->getSingleScalarResult() ?: 0;
    }

    private function getTransactionsPerDay(EntityManagerInterface $entityManager, int $days): array {
        $entries = [];

        $begin = new DateTime(\sprintf('-%d day', $days));
        $dateBegin = $begin->format('Y-m-d 00:00:00');
        $end = new DateTime('tomorrow');

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end);

        foreach ($period as $dt) {
            $date = $dt->format('Y-m-d');

            $entries[$date] = [
                'date' => $date,
                'transactions' => 0,
                'distinctUsers' => 0,
                'balance' => 0,
                'charged' => 0,
                'spent' => 0,
            ];
        }

        $results = $entityManager
            ->createQueryBuilder()
            ->select([
                'DATE(t.created) as createDate',
                'COUNT(t.id) as countTransactions',
                'SUM((CASE WHEN t.amount >= 0 THEN 1 ELSE 0 END)) as countCharged',
                'SUM((CASE WHEN t.amount < 0 THEN 1 ELSE 0 END)) as countSpent',
                'COUNT(DISTINCT t.user) as distinctUsers',
                'SUM(t.amount) as amount',
                'SUM((CASE WHEN t.amount >= 0 THEN t.amount ELSE 0 END)) as amountCharged',
                'SUM((CASE WHEN t.amount < 0 THEN t.amount ELSE 0 END)) as amountSpent',
            ])
            ->from(Transaction::class, 't')
            ->where('t.created >= :created')
            ->setParameter('created', $dateBegin)
            ->groupBy('createDate')
            ->orderBy('createDate')
            ->getQuery()
            ->getArrayResult();

        foreach ($results as $result) {
            $key = $result['createDate'];

            $entries[$key] = array_merge($entries[$key], [
                'date' => $result['createDate'],
                'transactions' => (int) $result['countTransactions'],
                'distinctUsers' => (int) $result['distinctUsers'],
                'balance' => (int) $result['amount'],

                'charged' => [
                    'amount' => (int) $result['amountCharged'],
                    'transactions' => (int) $result['countCharged'],
                ],

                'spent' => [
                    'amount' => (int) $result['amountSpent'] * -1,
                    'transactions' => (int) $result['countSpent'],
                ],
            ]);
        }

        return array_values(array_reverse($entries));
    }

    private function getUserCount(EntityManagerInterface $entityManager): int {
        return $entityManager->createQueryBuilder()
            ->select('COUNT(u) as count')
            ->from(User::class, 'u')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getUserTransactionBaseQuery(User $user, EntityManagerInterface $entityManager): QueryBuilder {
        return $entityManager->createQueryBuilder()
            ->select('COUNT(t.id) as count, SUM(t.amount) amount')
            ->from(Transaction::class, 't')
            ->where('t.user = :user')
            ->andWhere('t.deleted = false')
            ->groupBy('t.user')
            ->setParameter('user', $user);
    }
}
