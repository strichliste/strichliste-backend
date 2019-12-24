<?php

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Article|null find($id, $lockMode = null, $lockVersion = null)
 * @method Article|null findOneBy(array $criteria, array $orderBy = null)
 * @method Article[]    findAll()
 * @method Article[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleRepository extends ServiceEntityRepository {

    function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Article::class);
    }

    function findOneActive($id) {
        return $this->findOneBy(['active' => true, 'id' => $id]);
    }

    function findOneActiveBy(array $criteria): ?Article {
        $criteria = array_merge(['active' => true], $criteria);

        return $this->findOneBy($criteria);
    }

    function countActive(): int {
        return $this->count([
            'active' => true
        ]);
    }
}
