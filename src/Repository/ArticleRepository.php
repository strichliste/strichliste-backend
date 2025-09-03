<?php

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method null|Article find($id, $lockMode = null, $lockVersion = null)
 * @method null|Article findOneBy(array $criteria, array $orderBy = null)
 * @method Article[]    findAll()
 * @method Article[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Article::class);
    }

    public function findOneActive($id) {
        return $this->findOneBy(['active' => true, 'id' => $id]);
    }

    public function findOneActiveBy(array $criteria): ?Article {
        $criteria = array_merge(['active' => true], $criteria);

        return $this->findOneBy($criteria);
    }

    public function countActive(): int {
        return $this->count([
            'active' => true,
        ]);
    }
}
