<?php

namespace App\Service;

use App\Entity\Article;
use App\Event\ArticleCreatedEvent;
use App\Event\ArticleDeletedEvent;
use App\Event\ArticleUpdatedEvent;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class ArticleService
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
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

            $result = $article;
        } else {
            $result = $this->entityManager->wrapInTransaction(function () use ($article, $name, $amountCents, $active) {
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

        $this->eventDispatcher->dispatch(new ArticleUpdatedEvent($result));

        return $result;
    }

    /**
     * Create and persist a new article. Input is validated upstream by
     * {@see \App\Dto\Api\WriteArticleDto}; `$amountCents` is raw integer cents.
     */
    public function create(string $name, int $amountCents): Article
    {
        $article = new Article();
        $article->setName($name);
        $article->setAmount($amountCents);

        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new ArticleCreatedEvent($article));

        return $article;
    }

    /**
     * Soft-delete an article (sets isActive=false). Any barcode cleanup is the
     * caller's concern — it differs between the API and the UI.
     *
     * No explicit persist(): the article is always already managed here, and a
     * persist() would cascade onto the (in-memory) barcode collection and revive
     * barcodes a caller scheduled for removal.
     */
    public function deactivate(Article $article): Article
    {
        $article->setActive(false);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new ArticleDeletedEvent($article));

        return $article;
    }
}
