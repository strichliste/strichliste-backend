<?php

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Tag|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tag|null findOneBy(array $criteria, array $orderBy = null)
 * @method Tag[]    findAll()
 * @method Tag[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TagRespository extends ServiceEntityRepository {

    function __construct(RegistryInterface $registry) {
        parent::__construct($registry, Tag::class);
    }

    function findByArticleIdAndTag(int $articleId, string $tag): ?Tag {
        return $this->findOneBy(['article' => $articleId, 'tag' => $tag]);
    }
}
