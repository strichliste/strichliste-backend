<?php

namespace App\Service;

use App\Entity\Article;
use App\Exception\ParameterMissingException;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class ArticleService
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private EntityManagerInterface $entityManager,
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

    // request-bound adapter for the legacy REST controller
    public function updateArticle(Request $request, Article $article): Article
    {
        $newArticle = $this->createArticleByRequest($request);

        return $this->update($article, $newArticle->getName(), $newArticle->getAmount());
    }

    /**
     * @throws ParameterMissingException
     */
    public function createArticleByRequest(Request $request): Article
    {
        $name = $request->request->get('name');
        if (!$name) {
            throw new ParameterMissingException('name');
        }

        $amount = (int) $request->request->get('amount', 0);
        if (!$amount) {
            throw new ParameterMissingException('amount');
        }

        $article = new Article();
        $article->setName(trim($name));
        $article->setAmount($amount);

        return $article;
    }
}
