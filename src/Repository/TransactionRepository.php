<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Transaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function findAll(int $offset = 0, int $limit = 25) {
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
    public function findByUser(User $user, int $offset = 0, int $limit = 25) {
        return $this->createQueryBuilder('t')
            ->select('t')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByUserAndId(User $user, int $transactionId): ?Transaction {
        return $this->createQueryBuilder('t')
            ->select('t')
            ->where('t.id = :id')
            ->andWhere('t.user = :user')
            ->setParameters([
                'id' => $transactionId,
                'user' => $user
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }
}
