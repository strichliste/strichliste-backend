<?php

namespace App\Controller;

use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/article")
 */
class ArticleController extends AbstractController {

    /**
     * @Route("/", methods="GET")
     */
    public function list(EntityManagerInterface $entityManager) {
        return $this->json([
            'articles' => $entityManager->getRepository(Article::class)->findAllActive(),
        ]);
    }

    /**
     * @Route("/", methods="POST")
     */
    public function createArticle(Request $request, EntityManagerInterface $entityManager) {
        $article = $this->createArticleByRequest($request);

        $entityManager->persist($article);
        $entityManager->flush();

        return $this->json([
            'article' => $article
        ]);
    }

    /**
     * @Route("/{articleId}", methods="GET")
     */
    public function getArticle($articleId, EntityManagerInterface $entityManager) {
        $article = $entityManager->getRepository(Article::class)->find($articleId);

        if (!$article) {
            throw $this->createNotFoundException();
        }

        return $this->json([
            'article' => $article
        ]);
    }

    /**
     * @Route("/{articleId}", methods="POST")
     */
    public function updateArticle($articleId, Request $request, EntityManagerInterface $entityManager) {
        $oldArticle = $entityManager->getRepository(Article::class)->find($articleId);

        if (!$oldArticle) {
            throw $this->createNotFoundException();
        }

        $newArticle = $this->createArticleByRequest($request);
        $newArticle->setPrecursor($oldArticle);

        $oldArticle->setActive(false);

        $entityManager->transactional(function () use ($entityManager, $oldArticle, $newArticle) {
            $entityManager->persist($oldArticle);
            $entityManager->persist($newArticle);
        });

        $entityManager->flush();

        return $this->json([
            'article' => $newArticle
        ]);
    }

    /**
     * @Route("/{articleId}", methods="DELETE")
     */
    public function deleteArticle($articleId, EntityManagerInterface $entityManager) {
        $article = $entityManager->getRepository(Article::class)->find($articleId);

        if (!$article) {
            throw $this->createNotFoundException();
        }

        $article->setActive(false);

        $entityManager->persist($article);
        $entityManager->flush();

        return $this->json([
            'article' => $article
        ]);
    }

    private function createArticleByRequest(Request $request): Article {

        $name = $request->request->get('name');
        if (!$name) {
            throw new BadRequestHttpException('Missing parameter name');
        }

        $amount = $request->request->get('amount');
        if (!$amount) {
            throw new BadRequestHttpException('Missing parameter amount');
        }

        $article = new Article();
        $article->setName($name);
        $article->setAmount($amount);
        $article->setActive(true);

        $barcode = $request->request->get('barcode');
        if ($barcode) {
            $article->setBarcode($barcode);
        }

        return $article;
    }
}
