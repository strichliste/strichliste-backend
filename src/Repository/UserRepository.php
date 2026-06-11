<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    #[\Override]
    public function findAll(): array
    {
        return $this->getBaseQueryBuilder()
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<User>
     */
    public function findAllInactive(\DateTime $since): array
    {
        return $this->getBaseQueryBuilder()
            ->andWhere('(u.updated IS NULL or u.updated <= :since)')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<User>
     */
    public function findAllActive(\DateTime $since): array
    {
        return $this->getBaseQueryBuilder()
            ->andWhere('u.updated IS NOT NULL')
            ->andWhere('u.updated >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{users: list<User>, total: int}
     */
    public function findAllActivePaginated(\DateTime $since, int $limit, int $offset): array
    {
        $base = $this->getBaseQueryBuilder()
            ->andWhere('u.updated IS NOT NULL')
            ->andWhere('u.updated >= :since')
            ->setParameter('since', $since);

        $total = (int) (clone $base)
            ->select('COUNT(u.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()->getSingleScalarResult();

        $users = $base
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()->getResult();

        return ['users' => $users, 'total' => $total];
    }

    /**
     * @return array{users: list<User>, total: int}
     */
    public function findAllInactivePaginated(\DateTime $since, int $limit, int $offset): array
    {
        $base = $this->getBaseQueryBuilder()
            ->andWhere('(u.updated IS NULL or u.updated <= :since)')
            ->setParameter('since', $since);

        $total = (int) (clone $base)
            ->select('COUNT(u.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()->getSingleScalarResult();

        $users = $base
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()->getResult();

        return ['users' => $users, 'total' => $total];
    }

    public function findByIdentifier(int|string $identifier): ?User
    {
        if (is_numeric($identifier)) {
            return $this->find($identifier);
        }

        return $this->findByName($identifier);
    }

    public function findByName(string $name): ?User
    {
        return $this->findOneBy(['name' => $name]);
    }

    private function getBaseQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('u')
            ->select('u')
            ->where('u.disabled = false')
            ->orderBy('u.name');
    }
}
