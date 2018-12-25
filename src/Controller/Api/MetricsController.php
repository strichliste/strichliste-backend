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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MetricsController extends AbstractController {

    /**
     * @Route("/api/metrics", methods="GET")
     */
    function metrics(EntityManagerInterface $entityManager) {
        return $this->json([
            'balance' => $this->getBalance($entityManager),
            'transactionCount' => $this->getTransactionCount($entityManager),
            'userCount' => $this->getUserCount($entityManager),
            'days' => $this->getTransactionsPerDay($entityManager)
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
                    'article' => $articleSerializer->serialize($article['article']),
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

    private function getBalance(EntityManagerInterface $entityManager) {
        return (int)$entityManager->createQueryBuilder()
            ->select('SUM(u.balance) as balance')
            ->from(User::class, 'u')
            ->where('u.active = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getTransactionCount(EntityManagerInterface $entityManager): int {
        return $entityManager->createQueryBuilder()
            ->select('COUNT(t) as count')
            ->from(Transaction::class, 't')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getTransactionsPerDay(EntityManagerInterface $entityManager) {

        $stmt = $entityManager->getConnection()->prepare(
            "select 
                DATE(created) as date,
                COUNT(id) as count,
                COUNT(DISTINCT user_id) as distinctUsers,
                SUM(amount) as balance
             from 
                transactions
             group by DATE(created)");

        $stmt->execute();
        $entries = $stmt->fetchAll();

        $entries = array_map(function ($entry) use ($entityManager) {
            $entry['positiveBalance'] = (int)$entityManager
                ->createQueryBuilder()
                ->select('SUM(t.amount)')
                ->from(Transaction::class, 't')
                ->where('t.amount > 0')
                ->getQuery()
                ->getSingleScalarResult();

            $entry['negativeBalance'] = (int)$entityManager
                ->createQueryBuilder()
                ->select('SUM(t.amount)')
                ->from(Transaction::class, 't')
                ->where('t.amount < 0')
                ->getQuery()
                ->getSingleScalarResult();

            $entry['balance'] = (int)$entry['balance'];

            return $entry;
        }, $entries);

        return $entries;
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
