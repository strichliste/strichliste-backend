<?php

namespace App\Controller\Api;

use App\Entity\Article;
use App\Entity\ArticleTag;
use App\Entity\Tag;
use App\Exception\ArticleNotFoundException;
use App\Exception\ArticleTagAlreadyExistsException;
use App\Exception\ParameterInvalidException;
use App\Exception\TagNotFoundException;
use App\Repository\TagRepository;
use App\Serializer\ArticleSerializer;
use App\Serializer\TagSerializer;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class TagController extends AbstractController
{
    public function __construct(private readonly TagSerializer $tagSerializer)
    {
    }

    #[Route('/tag', methods: ['GET'])]
    #[OA\Get(
        summary: 'List all tags',
        tags: ['tag'],
        responses: [
            new OA\Response(response: 200, description: 'All tags.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'count', type: 'integer'),
                new OA\Property(property: 'tags', type: 'array', items: new OA\Items(ref: '#/components/schemas/Tag')),
            ])),
        ],
    )]
    public function listTags(EntityManagerInterface $entityManager): JsonResponse
    {
        $tags = $entityManager->getRepository(Tag::class)->findAll();

        usort($tags, fn (Tag $a, Tag $b) => ($b->getUsageCount() <=> $a->getUsageCount())
                ?: ($b->getCreated() <=> $a->getCreated())
        );

        return $this->json([
            'count' => count($tags),
            'tags' => array_map($this->tagSerializer->serialize(...), $tags),
        ]);
    }

    #[Route('/article/{articleId}/tag', methods: ['GET'])]
    #[OA\Get(
        summary: 'List an article\'s tags',
        tags: ['tag'],
        parameters: [
            new OA\Parameter(name: 'articleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The article\'s tags.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'count', type: 'integer'),
                new OA\Property(property: 'tags', type: 'array', items: new OA\Items(ref: '#/components/schemas/Tag')),
            ])),
            new OA\Response(response: 404, ref: '#/components/responses/Error'),
        ],
    )]
    public function listArticleTags(int $articleId, EntityManagerInterface $entityManager): JsonResponse
    {
        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        $tags = $article->getTags();

        return $this->json([
            'count' => count($tags),
            'tags' => array_map($this->tagSerializer->serialize(...), $tags),
        ]);
    }

    #[Route('/article/{articleId}/tag/{tagId}', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a single tag of an article',
        tags: ['tag'],
        parameters: [
            new OA\Parameter(name: 'articleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tagId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The tag.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'tag', ref: '#/components/schemas/Tag'),
            ])),
            new OA\Response(response: 404, ref: '#/components/responses/Error'),
        ],
    )]
    public function getArticleTag(int $articleId, int $tagId, EntityManagerInterface $entityManager): JsonResponse
    {
        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        $articleTag = $entityManager->getRepository(ArticleTag::class)->findOneBy(['article' => $articleId, 'tag' => $tagId]);
        if (!$articleTag) {
            throw new TagNotFoundException($tagId);
        }

        return $this->json([
            'tag' => $this->tagSerializer->serialize($articleTag->getTag()),
        ]);
    }

    #[Route('/article/{articleId}/tag', methods: ['POST'])]
    #[OA\Post(
        summary: 'Add a tag to an article',
        tags: ['tag'],
        parameters: [
            new OA\Parameter(name: 'articleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['tag'],
            properties: [
                new OA\Property(property: 'tag', type: 'string'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'The article including the new tag.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'article', ref: '#/components/schemas/Article'),
            ])),
            new OA\Response(response: 400, ref: '#/components/responses/Error'),
            new OA\Response(response: 404, ref: '#/components/responses/Error'),
        ],
    )]
    public function addArticleTag(int $articleId, Request $request, ArticleSerializer $articleSerializer, EntityManagerInterface $entityManager, TagRepository $tagRepository): JsonResponse
    {
        $tag = trim($request->request->getString('tag'));
        if (!$tag) {
            throw new ParameterInvalidException('tag');
        }

        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        $newTag = new Tag($tag);
        $existingTag = $tagRepository->findByTag($tag);
        if ($existingTag) {
            if ($article->hasTag($existingTag)) {
                throw new ArticleTagAlreadyExistsException($article, $existingTag);
            }

            $newTag = $existingTag;
        }

        $article->addTag($newTag);

        $entityManager->persist($article);
        $entityManager->flush();

        return $this->json([
            'article' => $articleSerializer->serialize($article),
        ]);
    }

    #[Route('/article/{articleId}/tag/{tagId}', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Remove a tag from an article',
        tags: ['tag'],
        parameters: [
            new OA\Parameter(name: 'articleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tagId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The article without the removed tag.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'article', ref: '#/components/schemas/Article'),
            ])),
            new OA\Response(response: 404, ref: '#/components/responses/Error'),
        ],
    )]
    public function deleteArticleTag(int $articleId, int $tagId, ArticleSerializer $articleSerializer, EntityManagerInterface $entityManager): JsonResponse
    {
        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        $articleTag = $entityManager->getRepository(ArticleTag::class)->findOneBy(['article' => $articleId, 'tag' => $tagId]);
        if (!$articleTag) {
            throw new TagNotFoundException($tagId);
        }

        $entityManager->wrapInTransaction(function () use ($entityManager, $articleTag) {
            $entityManager->remove($articleTag);

            $tag = $articleTag->getTag();
            if (1 === $tag->getUsageCount()) {
                $entityManager->remove($tag);
            }

            $entityManager->flush();
        });

        return $this->json([
            'article' => $articleSerializer->serialize($article),
        ]);
    }
}
