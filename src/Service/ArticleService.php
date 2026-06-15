<?php

namespace App\Service;

use App\Entity\Article;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

class ArticleService
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Used articles get archived and replaced via the precursor chain; returns
     * the article callers should use afterwards.
     *
     * @param bool|null $active null = leave unchanged
     */
    public function update(Article $article, string $name, int $amountCents, ?bool $active = null): Article
    {
        $referenceCount = $this->transactionRepository->getArticleReferenceCount($article);

        if (0 === $referenceCount) {
            $article->setName($name);
            $article->setAmount($amountCents);
            if (null !== $active) {
                $article->setActive($active);
            }
            $this->entityManager->persist($article);
            $this->entityManager->flush();

            return $article;
        }

        return $this->entityManager->wrapInTransaction(function () use ($article, $name, $amountCents, $active) {
            $new = new Article();
            $new->setName($name);
            $new->setAmount($amountCents);
            $new->setPrecursor($article);
            $new->setUsageCount($article->getUsageCount());
            $new->setActive($active ?? true);
            $this->entityManager->persist($new);

            foreach ($article->getBarcodes() as $barcode) {
                $barcode->setArticle($new);
                $this->entityManager->persist($barcode);
            }
            foreach ($article->getArticleTags() as $articleTag) {
                $articleTag->setArticle($new);
                $this->entityManager->persist($articleTag);
            }
            $article->setActive(false);
            $this->entityManager->persist($article);

            $this->entityManager->flush();

            return $new;
        });
    }

    /**
     * Build a new (unpersisted) article. Input is validated upstream by
     * {@see \App\Dto\Api\WriteArticleDto}; `$amountCents` is raw integer cents.
     */
    public function create(string $name, int $amountCents): Article
    {
        $article = new Article();
        $article->setName($name);
        $article->setAmount($amountCents);

        return $article;
    }
}
