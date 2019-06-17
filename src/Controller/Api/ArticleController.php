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

        $criteria = [
            'active' => $request->query->getBoolean('active', true)
        ];

        $barcode = $request->query->get('barcode');
        if ($barcode) {
            $criteria['barcode'] = $barcode;
        }

        $repository = $entityManager->getRepository(Article::class);
        $articles = $repository->findBy($criteria, ['name' => 'ASC'], $limit, $offset);

        return $this->json([
            'count' => $repository->countActive(),
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
     * @Route("/search", methods="GET")
     */
    function search(Request $request, EntityManagerInterface $entityManager) {
        $query = $request->query->get('query');
        $limit = $request->query->get('limit', 25);
        $barcode = $request->query->get('barcode');

        $queryBuilder = $entityManager->getRepository(Article::class)->createQueryBuilder('a');

        if ($barcode) {
            $query = false;

            $queryBuilder
                ->where('a.barcode = :barcode')
                ->setParameter('barcode', $barcode);
        }

        if ($query) {
            $queryBuilder
                ->where('a.barcode = :barcode')
                ->orWhere('a.name LIKE :query')
                ->setParameter('barcode', $query)
                ->setParameter('query', '%' . $query . '%');
        }


        $results = $queryBuilder
            ->andWhere('a.active = true')
            ->orderBy('a.name')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'count' => count($results),
            'articles' => array_map(function (Article $article) {
                return $this->articleSerializer->serialize($article);
            }, $results),
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
