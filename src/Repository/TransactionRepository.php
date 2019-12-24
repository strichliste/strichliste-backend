<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Transaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransactionRepository extends ServiceEntityRepository {

    function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Transaction::class);
    }

    function findAll($limit = null, $offset = null) {
        return $this->createQueryBuilder('t')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param User $user
     * @param int $offset
     * @param int $limit
     * @return Transaction[]
     */
    function findByUser(User $user, $limit = null, $offset = null) {
        return $this->findBy(['user' => $user], ['id' => 'DESC'], $limit, $offset);
    }

    function findByUserAndId(User $user, int $transactionId): ?Transaction {
        return $this->findOneBy(['id' => $transactionId, 'user' => $user]);
    }

    function getArticleReferenceCount(Article $article): int {
        return $this->createQueryBuilder('t')
            ->select('count(t.id)')
            ->where('t.article = :article')
            ->setParameter('article', $article)
            ->getQuery()
            ->getSingleScalarResult();
    }

    function countByUser(User $user): int {
        return $this->count([
            'user' => $user
        ]);
    }
}
