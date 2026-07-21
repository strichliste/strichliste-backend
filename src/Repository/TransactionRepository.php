<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
        return $this->listQueryBuilder()
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
        return $this->listQueryBuilder()
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // Fetch-join the to-one links every transaction serializes, so a page of
    // transactions costs one query instead of one per row per association.
    // Only to-one joins here — they don't multiply rows, so LIMIT stays correct.
    // ponytail: the embedded article's barcodes/tags collections still lazy-load
    // per row (O(rows) queries); fetching them needs a Paginator to keep paging.
    private function listQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->addSelect('u', 'a', 'rt', 'ru', 'st', 'su')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.article', 'a')
            ->leftJoin('t.recipientTransaction', 'rt')
            ->leftJoin('rt.user', 'ru')
            ->leftJoin('t.senderTransaction', 'st')
            ->leftJoin('st.user', 'su');
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
