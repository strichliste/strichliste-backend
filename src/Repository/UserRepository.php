<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository {

    public function __construct(RegistryInterface $registry) {
        parent::__construct($registry, User::class);
    }

    public function findAllActive(\DateTime $since = null): array {
        $queryBuilder = $this->createQueryBuilder('u')
            ->select('u')
            ->where('u.active = true');

        if ($since) {
            $queryBuilder
                ->andWhere('u.updated IS NOT NULL')
                ->andWhere('u.updated >= :since')
                ->setParameter('since', $since);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function findAllActiveAndStale(\DateTime $since): array {
        return $this->createQueryBuilder('u')
            ->select('u')
            ->where('u.active = true')
            ->andWhere('(u.updated IS NULL or u.updated <= :since)')
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
}
