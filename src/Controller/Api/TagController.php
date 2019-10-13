<?php

namespace App\Controller\Api;

use App\Entity\Article;
use App\Entity\Tag;
use App\Exception\ArticleNotFoundException;
use App\Exception\ArticleTagAlreadyExistsException;
use App\Exception\BarcodeNotFoundException;
use App\Exception\TagNotFoundException;
use App\Serializer\ArticleSerializer;
use App\Serializer\TagSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api")
 */
class TagController extends AbstractController {

    private $tagSerializer;

    function __construct(TagSerializer $tagSerializer) {
        $this->tagSerializer = $tagSerializer;
    }

    /**
     * @Route("/tag", methods="GET")
     */
    function listTags(EntityManagerInterface $entityManager) {
        $tags = $entityManager->getRepository(Tag::class)->findBy([], ['created' => 'DESC']);

        return $this->json([
            'count' => count($tags),
            'tags' => array_map(function (Tag $tag) {
                return $this->tagSerializer->serialize($tag);
            }, $tags),
        ]);
    }

    /**
     * @Route("/article/{articleId}/tag", methods="GET")
     */
    function listArticleBarcode(int $articleId, EntityManagerInterface $entityManager) {
        $article = $entityManager->getRepository(Article::class)->find($articleId);
        $tags = $article->getTags()->getValues();

        return $this->json([
            'count' => count($tags),
            'tags' => array_map(function (Tag $tag) {
                return $this->tagSerializer->serialize($tag);
            }, $tags),
        ]);
    }

    /**
     * @Route("/article/{articleId}/tag/{tagId}", methods="GET")
     */
    function getArticleTag(int $articleId, int $tagId, EntityManagerInterface $entityManager) {
        $tag = $entityManager->getRepository(Tag::class)->find($tagId);
        if (!$tag) {
            throw new BarcodeNotFoundException($tagId);
        }

        return $this->json([
            'tag' => $this->tagSerializer->serialize($tag)
        ]);
    }

    /**
     * @Route("/article/{articleId}/tag", methods="POST")
     */
    function addArticleTag(int $articleId, Request $request, ArticleSerializer $articleSerializer, EntityManagerInterface $entityManager) {
        $tag = $request->request->get('tag');

        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        $existingTag = $entityManager->getRepository(Tag::class)->findByArticleIdAndTag($articleId, $tag);
        if ($existingTag) {
            throw new ArticleTagAlreadyExistsException($existingTag);
        }

        $article->addTag(new Tag($tag));

        $entityManager->persist($article);
        $entityManager->flush();

        return $this->json([
            'article' => $articleSerializer->serialize($article)
        ]);
    }

    /**
     * @Route("/article/{articleId}/barcode/{tagId}", methods="DELETE")
     */
    function deleteArticleTag(int $articleId, int $tagId, ArticleSerializer $articleSerializer, EntityManagerInterface $entityManager) {
        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        $existingTag = $entityManager->getRepository(Tag::class)->find($tagId);
        if (!$existingTag) {
            throw new TagNotFoundException($tagId);
        }

        $entityManager->remove($existingTag);
        $entityManager->flush();

        return $this->json([
            'article' => $articleSerializer->serialize($article)
        ]);
    }
}
