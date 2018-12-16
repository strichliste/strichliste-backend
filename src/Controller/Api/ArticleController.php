<?php

namespace App\Controller\Api;

use App\Entity\Article;
use App\Exception\ArticleBarcodeAlreadyExistsException;
use App\Exception\ArticleInactiveException;
use App\Exception\ArticleNotFoundException;
use App\Exception\ParameterMissingException;
use App\Serializer\ArticleSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/article")
 */
class ArticleController extends AbstractController {

    /**
     * @var ArticleSerializer
     */
    private $articleSerializer;

    function __construct(ArticleSerializer $articleSerializer) {
        $this->articleSerializer = $articleSerializer;
    }

    /**
     * @Route(methods="GET")
     */
    function list(Request $request, EntityManagerInterface $entityManager) {
        $limit = $request->query->get('limit', 25);
        $offset = $request->query->get('offset');

        $repository = $entityManager->getRepository(Article::class);

        $barcode = $request->query->get('barcode');
        if ($barcode) {
            $articles = $repository->findActiveBy(['barcode' => $barcode]);
        } else {
            $articles = $repository->findAllActive($limit, $offset);
        }

        $count = $entityManager->getRepository(Article::class)->countActive();

        return $this->json([
            'count' => $count,
            'articles' => array_map(function (Article $article) {
                return $this->articleSerializer->serialize($article);
            }, $articles),
        ]);
    }

    /**
     * @Route(methods="POST")
     */
    function createArticle(Request $request, EntityManagerInterface $entityManager) {
        $article = $this->createArticleByRequest($request);

        if ($article->getBarcode()) {
            $existingArticle = $entityManager->getRepository(Article::class)->findOneActiveBy([
                'barcode' => $article->getBarcode()
            ]);

            if ($existingArticle) {
                throw new ArticleBarcodeAlreadyExistsException($existingArticle);
            }
        }

        $entityManager->persist($article);
        $entityManager->flush();

        return $this->json([
            'article' => $this->articleSerializer->serialize($article),
        ]);
    }

    /**
     * @Route("/{articleId}", methods="GET")
     */
    function getArticle($articleId, EntityManagerInterface $entityManager) {

        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        return $this->json([
            'article' => $this->articleSerializer->serialize($article),
        ]);
    }

    /**
     * @Route("/{articleId}", methods="POST")
     */
    function updateArticle($articleId, Request $request, EntityManagerInterface $entityManager) {
        $oldArticle = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$oldArticle) {
            throw new ArticleNotFoundException($articleId);
        }

        if (!$oldArticle->isActive()) {
            throw new ArticleInactiveException($oldArticle);
        }

        $newArticle = $this->createArticleByRequest($request);
        $newArticle->setPrecursor($oldArticle);
        $newArticle->setUsageCount($oldArticle->getUsageCount());

        if ($newArticle->getBarcode()) {
            $existingArticle = $entityManager->getRepository(Article::class)->findOneActiveBy([
                'barcode' => $newArticle->getBarcode()
            ]);

            if ($existingArticle && $existingArticle->getId() != $oldArticle->getId()) {
                throw new ArticleBarcodeAlreadyExistsException($existingArticle);
            }
        }

        $oldArticle->setActive(false);

        $entityManager->transactional(function () use ($entityManager, $oldArticle, $newArticle) {
            $entityManager->persist($oldArticle);
            $entityManager->persist($newArticle);
        });

        $entityManager->flush();

        return $this->json([
            'article' => $this->articleSerializer->serialize($newArticle),
        ]);
    }

    /**
     * @Route("/{articleId}", methods="DELETE")
     */
    function deleteArticle($articleId, EntityManagerInterface $entityManager) {
        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        $article->setActive(false);

        $entityManager->persist($article);
        $entityManager->flush();

        return $this->json([
            'article' => $this->articleSerializer->serialize($article),
        ]);
    }

    /**
     * @param Request $request
     * @return Article
     * @throws ParameterMissingException
     */
    private function createArticleByRequest(Request $request): Article {

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
