<?php

namespace App\Repository;

use App\Entity\User;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method null|User find($id, $lockMode = null, $lockVersion = null)
 * @method null|User findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, User::class);
    }

    public function findAll(): array {
        return $this->getBaseQueryBuilder()
            ->getQuery()
            ->getResult();
    }

    public function findAllDisabled(): array {
        return $this->createQueryBuilder('u')
            ->where('u.disabled = true')
            ->orderBy('u.name')
            ->getQuery()
            ->getResult();
    }

    public function findAllInactive(DateTime $since): array {
        return $this->getBaseQueryBuilder()
            ->andWhere('(u.updated IS NULL or u.updated <= :since)')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }

    public function findAllActive(DateTime $since): array {
        return $this->getBaseQueryBuilder()
            ->andWhere('u.updated IS NOT NULL')
            ->andWhere('u.updated >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }

    public function findByIdentifier($identifier): ?User {
        if (is_numeric($identifier)) {
            return $this->find($identifier);
        }

        return $this->findByName($identifier);
    }

    public function findByName(string $name): ?User {
        return $this->findOneBy(['name' => $name]);
    }

    private function getBaseQueryBuilder(): QueryBuilder {
        return $this->createQueryBuilder('u')
            ->select('u')
            ->where('u.disabled = false')
            ->orderBy('u.name');
    }
}
