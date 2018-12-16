<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository {

    function __construct(RegistryInterface $registry) {
        parent::__construct($registry, User::class);
    }

    function findAll(): array {
        return $this->getBaseQueryBuilder()
            ->getQuery()
            ->getResult();
    }

    function findAllDisabled(): array {
        return $this->createQueryBuilder('u')
            ->where('u.disabled = true')
            ->orderBy('u.name')
            ->getQuery()
            ->getResult();
    }

    function findAllInactive(\DateTime $since): array {
        return $this->getBaseQueryBuilder()
            ->andWhere('(u.updated IS NULL or u.updated <= :since)')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }

    function findAllActive(\DateTime $since): array {
        return $this->getBaseQueryBuilder()
            ->andWhere('u.updated IS NOT NULL')
            ->andWhere('u.updated >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }

    function findByIdentifier($identifier): ?User {
        if (is_numeric($identifier)) {
            return $this->find($identifier);
        }

        return $this->findByName($identifier);
    }

    function findByName(string $name): ?User {
        return $this->findOneBy(['name' => $name]);
    }

    private function getBaseQueryBuilder(): QueryBuilder {
        return $this->createQueryBuilder('u')
            ->select('u')
            ->where('u.disabled = false')
            ->orderBy('u.name');
    }
}
