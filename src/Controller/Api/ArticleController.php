<?php

namespace App\Controller\Api;

use App\Entity\Article;
use App\Exception\ArticleBarcodeAlreadyExistsException;
use App\Exception\ArticleInactiveException;
use App\Exception\ArticleNotFoundException;
use App\Serializer\ArticleSerializer;
use App\Service\ArticleService;
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
    function createArticle(Request $request, ArticleService $articleService, EntityManagerInterface $entityManager) {
        $article = $articleService->createArticleByRequest($request);

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
    function getArticle($articleId, Request $request, EntityManagerInterface $entityManager) {
        $depth = $request->query->get('depth', 1);

        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        return $this->json([
            'article' => $this->articleSerializer->serialize($article, $depth),
        ]);
    }

    /**
     * @Route("/{articleId}", methods="POST")
     */
    function updateArticle($articleId, Request $request, ArticleService $articleService, EntityManagerInterface $entityManager) {
        $article = $entityManager->getRepository(Article::class)->find($articleId);

        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        if (!$article->isActive()) {
            throw new ArticleInactiveException($article);
        }

        $article = $articleService->updateArticle($request, $article);

        return $this->json([
            'article' => $this->articleSerializer->serialize($article),
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
}
