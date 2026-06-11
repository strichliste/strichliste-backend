<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr\Join;

// all amounts are integer cents
class MetricsService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function totalBalance(): int
    {
        return (int) ($this->em->createQueryBuilder()
            ->select('SUM(u.balance)')
            ->from(User::class, 'u')
            ->where('u.disabled = false')
            ->getQuery()->getSingleScalarResult() ?: 0);
    }

    // includes soft-deleted rows — the legacy /api/metrics count did too
    public function totalTransactionCount(): int
    {
        return (int) ($this->em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Transaction::class, 't')
            ->getQuery()->getSingleScalarResult() ?: 0);
    }

    public function totalUserCount(): int
    {
        return (int) ($this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->getQuery()->getSingleScalarResult() ?: 0);
    }

    /**
     * Daily activity for the last $days days, newest first.
     *
     * @param 'api'|'ui' $shape 'api' nests charged/spent as {amount, transactions}, 'ui' keeps them scalar
     */
    public function transactionsPerDay(int $days, string $shape = 'ui'): array
    {
        $begin = new \DateTime(sprintf('-%d day', $days));
        $dateBegin = $begin->format('Y-m-d 00:00:00');
        $end = new \DateTime('tomorrow');

        // legacy API: empty days keep scalar 0 for charged/spent, not the nested objects
        $emptyRow = ['transactions' => 0, 'distinctUsers' => 0, 'balance' => 0, 'charged' => 0, 'spent' => 0];

        $entries = [];
        $period = new \DatePeriod($begin, \DateInterval::createFromDateString('1 day'), $end);
        foreach ($period as $dt) {
            $key = $dt->format('Y-m-d');
            $entries[$key] = ['date' => $key] + $emptyRow;
        }

        $results = $this->em->createQueryBuilder()
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
            ->where('t.created >= :since')
            ->setParameter('since', $dateBegin)
            ->groupBy('createDate')
            ->orderBy('createDate')
            ->getQuery()->getArrayResult();

        foreach ($results as $r) {
            $row = [
                'date' => $r['createDate'],
                'transactions' => (int) $r['countTransactions'],
                'distinctUsers' => (int) $r['distinctUsers'],
                'balance' => (int) $r['amount'],
            ];
            if ('api' === $shape) {
                $row['charged'] = ['amount' => (int) $r['amountCharged'], 'transactions' => (int) $r['countCharged']];
                $row['spent'] = ['amount' => (int) $r['amountSpent'] * -1, 'transactions' => (int) $r['countSpent']];
            } else {
                $row['charged'] = (int) $r['amountCharged'];
                $row['spent'] = (int) $r['amountSpent'] * -1;
            }
            $entries[$r['createDate']] = $row;
        }

        return array_values(array_reverse($entries));
    }

    /**
     * @param bool $includeDeleted true preserves the legacy /api numbers, which counted reverted purchases
     */
    public function userArticles(User $user, int $limit = 10, bool $includeDeleted = false): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(a.id) as cnt, SUM(t.amount) * -1 as amt, a as article')
            ->from(Transaction::class, 't')
            ->innerJoin(Article::class, 'a', Join::WITH, 'a = t.article')
            ->where('t.user = :user')
            ->andWhere('t.article IS NOT NULL')
            ->setParameter('user', $user)
            ->groupBy('a')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults($limit);
        if (!$includeDeleted) {
            $qb->andWhere('t.deleted = false');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<array{cnt: int, amt: int, article: Article}>
     */
    public function topArticles(int $days, int $limit = 10): array
    {
        return $this->em->createQueryBuilder()
            ->select('COUNT(a.id) as cnt, SUM(t.amount) * -1 as amt, a as article')
            ->from(Transaction::class, 't')
            ->innerJoin(Article::class, 'a', Join::WITH, 'a = t.article')
            ->where('t.deleted = false')
            ->andWhere('t.created >= :since')
            ->setParameter('since', new \DateTimeImmutable("-{$days} days"))
            ->groupBy('a')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function userTransactionCount(User $user): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Transaction::class, 't')
            ->where('t.user = :user')->andWhere('t.deleted = false')
            ->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array{cnt: int, amount: int}
     */
    public function userOutgoing(User $user): array
    {
        $r = $this->em->createQueryBuilder()
            ->select('COUNT(t.id) as cnt, SUM(t.amount) as amount')
            ->from(Transaction::class, 't')
            ->where('t.user = :user')->andWhere('t.deleted = false')
            ->andWhere('t.recipientTransaction IS NOT NULL')
            ->setParameter('user', $user)
            ->groupBy('t.user')
            ->getQuery()->getOneOrNullResult(Query::HYDRATE_ARRAY);

        return ['cnt' => (int) ($r['cnt'] ?? 0), 'amount' => (int) ($r['amount'] ?? 0)];
    }

    /**
     * @return array{cnt: int, amount: int}
     */
    public function userIncoming(User $user): array
    {
        $r = $this->em->createQueryBuilder()
            ->select('COUNT(t.id) as cnt, SUM(t.amount) as amount')
            ->from(Transaction::class, 't')
            ->where('t.user = :user')->andWhere('t.deleted = false')
            ->andWhere('t.senderTransaction IS NOT NULL')
            ->setParameter('user', $user)
            ->groupBy('t.user')
            ->getQuery()->getOneOrNullResult(Query::HYDRATE_ARRAY);

        return ['cnt' => (int) ($r['cnt'] ?? 0), 'amount' => (int) ($r['amount'] ?? 0)];
    }
}
