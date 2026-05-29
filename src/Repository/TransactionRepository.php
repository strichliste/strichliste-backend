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

    function findAllPaginated($limit = null, $offset = null) {
        return $this->createQueryBuilder('t')
            // Stable sort so paging can't skip or duplicate rows across requests
            // (matches the id DESC ordering used by findByUser).
            ->orderBy('t.id', 'DESC')
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

    /**
     * Counts non-deleted transactions referencing this article. Drives the
     * precursor-vs-in-place update decision in `ArticleService::update`:
     * an article whose only references have been reverted is safe to edit
     * in place rather than archive.
     */
    function getArticleReferenceCount(Article $article): int {
        return (int) $this->createQueryBuilder('t')
            ->select('count(t.id)')
            ->where('t.article = :article')
            ->andWhere('t.deleted = false')
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
