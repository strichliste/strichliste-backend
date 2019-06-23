<?php

namespace App\Controller\Api;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\UserNotFoundException;
use App\Serializer\ArticleSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MetricsController extends AbstractController {

    /**
     * @Route("/api/metrics", methods="GET")
     */
    function metrics(Request $request, EntityManagerInterface $entityManager) {
        $days = $request->query->get('days', 30);

        return $this->json([
            'balance' => $this->getBalance($entityManager),
            'transactionCount' => $this->getTransactionCount($entityManager),
            'userCount' => $this->getUserCount($entityManager),
            'days' => $this->getTransactionsPerDay($entityManager, $days)
        ]);
    }

    /**
     * @Route("/api/user/{userId}/metrics", methods="GET")
     */

    function userMetrics($userId, ArticleSerializer $articleSerializer, EntityManagerInterface $entityManager) {
        /**
         * @var $user User
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

            'articles' => array_map(function ($article) use ($articleSerializer) {
                return [
                    'article' => $articleSerializer->serialize($article['article'], 0),
                    'count' => (int) $article['count'],
                    'amount' => (int) $article['amount'],
                 ];
            }, $articles),

            'transactions' => [
                'count' => (int) $transactionCount,
                'outgoing' => ['count' => (int) $outgoingTransactions['count'], 'amount' => (int) $outgoingTransactions['amount']],
                'incoming' => ['count' => (int) $incomingTransactions['count'], 'amount' => (int) $incomingTransactions['amount']],
            ]
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

        $begin = new \DateTime(sprintf('-%d day', $days));
        $dateBegin = $begin->format('Y-m-d 00:00:00');
        $end = new \DateTime('tomorrow');

        $interval = \DateInterval::createFromDateString('1 day');
        $period = new \DatePeriod($begin, $interval, $end);

        foreach ($period as $dt) {
            $date = $dt->format('Y-m-d');

            $entries[$date] = [
                'date' => $date,
                'transactions' => 0,
                'distinctUsers' => 0,
                'balance' => 0,
                'charged' => 0,
                'spent' => 0
            ];
        }

        $transactions = $this->getTransactionBaseSelect($entityManager, $dateBegin, $days)->getQuery()->getArrayResult();
        foreach($transactions as $transaction) {
            $key = $transaction['createDate'];

            $entries[$key] = array_merge($entries[$key], [
                'date' => $transaction['createDate'],
                'transactions' => (int) $transaction['countTransactions'],
                'distinctUsers' => (int) $transaction['distinctUsers'],
                'balance' => (int) $transaction['balance']
            ]);
        }

        $positiveTransactions = $this->getTransactionBaseSelect($entityManager, $dateBegin, $days)->andWhere('t.amount >= 0')
            ->getQuery()->getArrayResult();
        foreach($positiveTransactions as $transaction) {
            $key = $transaction['createDate'];

            $entries[$key] = array_merge($entries[$key], [
                'charged' => (int) $transaction['amount']
            ]);
        }

        $negativeTransactions = $this->getTransactionBaseSelect($entityManager, $dateBegin, $days)->andWhere('t.amount < 0')
            ->getQuery()->getArrayResult();
        foreach($negativeTransactions as $transaction) {
            $key = $transaction['createDate'];

            $entries[$key] = array_merge($entries[$key], [
                'spent' => (int) $transaction['amount'] * -1
            ]);
        }

        return array_values($entries);
    }

    private function getTransactionBaseSelect(EntityManagerInterface $entityManager, string $created, int $days): QueryBuilder {
        return $entityManager
            ->createQueryBuilder()
            ->select([
                'DATE(t.created) as createDate',
                'COUNT(t.id) as countTransactions',
                'COUNT(DISTINCT t.user) as distinctUsers',
                'SUM(t.amount) as balance'
            ])
            ->from(Transaction::class, 't')
            ->where('t.created >= :created')
            ->setParameter('created', $created)
            ->groupBy('createDate')
            ->orderBy('createDate');
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
