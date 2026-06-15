<?php

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    public function findOneActive(int|string $id): ?Article
    {
        return $this->findOneBy(['active' => true, 'id' => $id]);
    }

    public function countActive(): int
    {
        return $this->count([
            'active' => true,
        ]);
    }
}
