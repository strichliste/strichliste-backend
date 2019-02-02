<?php

namespace App\Service;


use App\Entity\Article;
use App\Exception\ArticleBarcodeAlreadyExistsException;
use App\Exception\ParameterMissingException;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class ArticleService {

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var TransactionRepository
     */
    private $transactionRepository;

    function __construct(TransactionRepository $transactionRepository, EntityManagerInterface $entityManager) {
        $this->transactionRepository = $transactionRepository;
        $this->entityManager = $entityManager;
    }

    function updateArticle(Request $request, Article $article): Article {
        $newArticle = $this->createArticleByRequest($request);

        $referenceCount = $this->transactionRepository->getArticleReferenceCount($article);

        if ($referenceCount == 0) {
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
                'barcode' => $newArticle->getBarcode()
            ]);

            if ($existingArticle && $existingArticle->getId() != $article->getId()) {
                throw new ArticleBarcodeAlreadyExistsException($existingArticle);
            }
        }

        $article->setActive(false);

        $this->entityManager->transactional(function () use ($article, $newArticle) {
            $this->entityManager->persist($article);
            $this->entityManager->persist($newArticle);
        });

        $this->entityManager->flush();

        return $newArticle;
    }

    /**
     * @param Request $request
     * @return Article
     * @throws ParameterMissingException
     */
    function createArticleByRequest(Request $request): Article {

        $name = $request->request->get('name');
        if (!$name) {
            throw new ParameterMissingException('name');
        }

        $amount = (int)$request->request->get('amount', 0);
        if (!$amount) {
            throw new ParameterMissingException('amount');
        }

        $article = new Article();
        $article->setName(trim($name));
        $article->setAmount($amount);

        $barcode = $request->request->get('barcode');
        if ($barcode) {
            $article->setBarcode(trim($barcode));
        }

        return $article;
    }
}