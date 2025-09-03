<?php

namespace App\Service;

use App\Entity\Article;
use App\Exception\ArticleBarcodeAlreadyExistsException;
use App\Exception\ParameterMissingException;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class ArticleService {
    public function __construct(private readonly TransactionRepository $transactionRepository, private readonly EntityManagerInterface $entityManager) {}

    public function updateArticle(Request $request, Article $article): Article {
        $newArticle = $this->createArticleByRequest($request);

        $referenceCount = $this->transactionRepository->getArticleReferenceCount($article);

        if ($referenceCount === 0) {
            $article->setName($newArticle->getName());
            $article->setAmount($newArticle->getAmount());
            $article->setActive($newArticle->isActive());
            $article->setBarcode($newArticle->getBarcode());

            $this->entityManager->persist($article);
            $this->entityManager->flush();

            return $article;
        }

        $newArticle->setPrecursor($article);
        $newArticle->setUsageCount($article->getUsageCount());

        if ($newArticle->getBarcode()) {
            $existingArticle = $this->entityManager->getRepository(Article::class)->findOneActiveBy([
                'barcode' => $newArticle->getBarcode(),
            ]);

            if ($existingArticle && $existingArticle->getId() !== $article->getId()) {
                throw new ArticleBarcodeAlreadyExistsException($existingArticle);
            }
        }

        $article->setActive(false);

        $this->entityManager->transactional(function () use ($article, $newArticle): void {
            $this->entityManager->persist($article);
            $this->entityManager->persist($newArticle);
        });

        $this->entityManager->flush();

        return $newArticle;
    }

    /**
     * @throws ParameterMissingException
     */
    public function createArticleByRequest(Request $request): Article {
        $name = $request->request->get('name');
        if (!$name) {
            throw new ParameterMissingException('name');
        }

        $amount = (int) $request->request->get('amount', 0);
        if ($amount === 0) {
            throw new ParameterMissingException('amount');
        }

        $article = new Article();
        $article->setName(mb_trim($name));
        $article->setAmount($amount);

        $barcode = $request->request->get('barcode');
        if ($barcode) {
            $article->setBarcode(mb_trim($barcode));
        }

        return $article;
    }
}
