<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * @return list<Transaction>
     */
    public function findAllPaginated(?int $limit = null, ?int $offset = null): array
    {
        return $this->createQueryBuilder('t')
            // stable paging; legacy clients saw PK-ascending order
            ->orderBy('t.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Transaction>
     */
    public function findByUser(User $user, ?int $limit = null, ?int $offset = null): array
    {
        return $this->findBy(['user' => $user], ['id' => 'DESC'], $limit, $offset);
    }

    // non-deleted only: a fully reverted article can be edited in place
    public function getArticleReferenceCount(Article $article): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('count(t.id)')
            ->where('t.article = :article')
            ->andWhere('t.deleted = false')
            ->setParameter('article', $article)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByUser(User $user): int
    {
        return $this->count([
            'user' => $user,
        ]);
    }
}
