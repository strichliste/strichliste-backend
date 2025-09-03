<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method null|Transaction find($id, $lockMode = null, $lockVersion = null)
 * @method null|Transaction findOneBy(array $criteria, array $orderBy = null)
 * @method Transaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransactionRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Transaction::class);
    }

    public function findAll($limit = null, $offset = null): array {
        return $this->createQueryBuilder('t')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Transaction[]
     */
    public function findByUser(User $user, ?int $limit = null, ?int $offset = null) {
        return $this->findBy(['user' => $user], ['id' => 'DESC'], $limit, $offset);
    }

    public function findByUserAndId(User $user, int $transactionId): ?Transaction {
        return $this->findOneBy(['id' => $transactionId, 'user' => $user]);
    }

    public function getArticleReferenceCount(Article $article): int {
        return $this->createQueryBuilder('t')
            ->select('count(t.id)')
            ->where('t.article = :article')
            ->setParameter('article', $article)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByUser(User $user): int {
        return $this->count([
            'user' => $user,
        ]);
    }
}
