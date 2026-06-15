<?php

namespace App\Controller\Api;

use App\ApiDoc\Article as ArticleSchema;
use App\ApiDoc\Error as ErrorSchema;
use App\Dto\Api\WriteArticleDto;
use App\Entity\Article;
use App\Entity\ArticleTag;
use App\Entity\Barcode;
use App\Entity\Tag;
use App\Exception\ArticleInactiveException;
use App\Exception\ArticleNotFoundException;
use App\Repository\ArticleRepository;
use App\Serializer\ArticleSerializer;
use App\Service\ArticleService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/article')]
class ArticleController extends AbstractController
{
    public function __construct(private readonly ArticleSerializer $articleSerializer)
    {
    }

    #[Route(methods: ['GET'])]
    #[OA\Get(
        summary: 'List articles',
        tags: ['article'],
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25)),
            new OA\Parameter(name: 'offset', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean', default: true)),
            new OA\Parameter(name: 'barcode', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'precursor', in: 'query', required: false, description: 'false = only articles without a precursor.', schema: new OA\Schema(type: 'boolean', default: true)),
            new OA\Parameter(name: 'ancestor', in: 'query', required: false, description: '"true" = only articles that have a successor revision, "false" = only ones without.', schema: new OA\Schema(type: 'string', enum: ['true', 'false'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Articles; `count` is the total number of ACTIVE articles.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'count', type: 'integer'),
                new OA\Property(property: 'articles', type: 'array', items: new OA\Items(ref: new Model(type: ArticleSchema::class))),
            ])),
        ],
    )]
    public function list(Request $request, EntityManagerInterface $entityManager, ArticleRepository $articleRepository): JsonResponse
    {
        $limit = $request->query->getInt('limit', 25);
        $offset = $request->query->has('offset') ? $request->query->getInt('offset') : null;
        $active = $request->query->getBoolean('active', true);

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('a1')
            ->from(Article::class, 'a1')
            ->leftJoin(Barcode::class, 'b', Join::WITH, 'b.article = a1')
            ->where('a1.active = :active')
            ->setParameter('active', $active);

        $barcode = trim($request->query->getString('barcode'));
        if ($barcode) {
            $queryBuilder
                ->andWhere('b.barcode = :barcode')
                ->setParameter('barcode', $barcode);
        }

        $precursor = $request->query->getBoolean('precursor', true);
        if (!$precursor) {
            $queryBuilder->andWhere('a1.precursor IS NULL');
        }

        $ancestor = $request->query->getString('ancestor');
        if ('true' === $ancestor) {
            $queryBuilder->leftJoin(Article::class, 'a2', Join::WITH, 'a2.precursor = a1.id')->andWhere('a2.id IS NOT NULL');
        } elseif ('false' === $ancestor) {
            $queryBuilder->leftJoin(Article::class, 'a2', Join::WITH, 'a2.precursor = a1.id')->andWhere('a2.id IS NULL');
        }

        $queryBuilder
            ->groupBy('a1')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->orderBy('a1.name', 'ASC');

        $articles = $queryBuilder->getQuery()->getResult();

        return $this->json([
            'count' => $articleRepository->countActive(),
            'articles' => array_map(fn (Article $article) => $this->articleSerializer->serialize($article), $articles),
        ]);
    }

    #[Route(methods: ['POST'])]
    #[OA\Post(
        summary: 'Create an article',
        tags: ['article'],
        requestBody: new OA\RequestBody(required: true, content: [
            new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(ref: new Model(type: WriteArticleDto::class))),
            new OA\MediaType(mediaType: 'application/x-www-form-urlencoded', schema: new OA\Schema(ref: new Model(type: WriteArticleDto::class))),
        ]),
        responses: [
            new OA\Response(response: 200, description: 'The created article.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'article', ref: new Model(type: ArticleSchema::class)),
            ])),
            new OA\Response(response: 422, description: 'Error envelope (shape shared by all 4xx responses).', content: new OA\JsonContent(ref: new Model(type: ErrorSchema::class))),
        ],
    )]
    public function createArticle(#[MapRequestPayload] WriteArticleDto $dto, ArticleService $articleService, EntityManagerInterface $entityManager): JsonResponse
    {
        $article = $articleService->create($dto->name, $dto->amount);

        $entityManager->persist($article);
        $entityManager->flush();

        return $this->json([
            'article' => $this->articleSerializer->serialize($article),
        ]);
    }

    #[Route('/search', methods: ['GET'])]
    #[OA\Get(
        summary: 'Search active articles',
        description: 'With `barcode` or `tag` the match is exact on that field; otherwise `query` matches barcode, tag or name substring.',
        tags: ['article'],
        parameters: [
            new OA\Parameter(name: 'query', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'barcode', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tag', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Matching active articles.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'count', type: 'integer'),
                new OA\Property(property: 'articles', type: 'array', items: new OA\Items(ref: new Model(type: ArticleSchema::class))),
            ])),
        ],
    )]
    public function search(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $query = $request->query->getString('query');
        $limit = $request->query->getInt('limit', 25);
        $barcode = trim($request->query->getString('barcode'));
        $tag = trim($request->query->getString('tag'));

        $queryBuilder = $entityManager
            ->getRepository(Article::class)
            ->createQueryBuilder('a')
            ->leftJoin(Barcode::class, 'b', Join::WITH, 'b.article = a')
            ->leftJoin(ArticleTag::class, 'at', Join::WITH, 'at.article = a')
            ->leftJoin(Tag::class, 't', Join::WITH, 'at.tag = t');

        if ($barcode) {
            $query = false;

            $queryBuilder
                ->where('b.barcode = :barcode')
                ->setParameter('barcode', $barcode);
        }

        if ($tag) {
            $query = false;

            $queryBuilder
                ->where('t.tag = :tag')
                ->setParameter('tag', $tag);
        }

        if ($query) {
            $queryBuilder
                ->where('b.barcode = :barcode')
                ->orWhere('t.tag = :tag')
                ->orWhere('a.name LIKE :query')
                ->setParameter('barcode', $query)
                ->setParameter('tag', $query)
                ->setParameter('query', '%'.$query.'%');
        }

        $results = $queryBuilder
            ->andWhere('a.active = true')
            ->orderBy('a.name')
            ->setMaxResults($limit)
            ->groupBy('a')
            ->getQuery()
            ->getResult();

        return $this->json([
            'count' => count($results),
            'articles' => array_map(fn (Article $article) => $this->articleSerializer->serialize($article), $results),
        ]);
    }

    #[Route('/{articleId}', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get an article',
        tags: ['article'],
        parameters: [
            new OA\Parameter(name: 'articleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'depth', in: 'query', required: false, description: 'How many precursor revisions to embed.', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The article.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'article', ref: new Model(type: ArticleSchema::class)),
            ])),
            new OA\Response(response: 404, description: 'Error envelope (shape shared by all 4xx responses).', content: new OA\JsonContent(ref: new Model(type: ErrorSchema::class))),
        ],
    )]
    public function getArticle(string $articleId, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $depth = $request->query->getInt('depth', 1);

        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        return $this->json([
            'article' => $this->articleSerializer->serialize($article, $depth),
        ]);
    }

    #[Route('/{articleId}', methods: ['POST'])]
    #[OA\Post(
        summary: 'Update an article (creates a new revision)',
        description: 'The existing article becomes the inactive precursor of the returned revision; only active articles can be updated.',
        tags: ['article'],
        parameters: [
            new OA\Parameter(name: 'articleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: [
            new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(ref: new Model(type: WriteArticleDto::class))),
            new OA\MediaType(mediaType: 'application/x-www-form-urlencoded', schema: new OA\Schema(ref: new Model(type: WriteArticleDto::class))),
        ]),
        responses: [
            new OA\Response(response: 200, description: 'The new active revision.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'article', ref: new Model(type: ArticleSchema::class)),
            ])),
            new OA\Response(response: 422, description: 'Error envelope (shape shared by all 4xx responses).', content: new OA\JsonContent(ref: new Model(type: ErrorSchema::class))),
            new OA\Response(response: 404, description: 'Error envelope (shape shared by all 4xx responses).', content: new OA\JsonContent(ref: new Model(type: ErrorSchema::class))),
        ],
    )]
    public function updateArticle(string $articleId, #[MapRequestPayload] WriteArticleDto $dto, ArticleService $articleService, EntityManagerInterface $entityManager): JsonResponse
    {
        $article = $entityManager->getRepository(Article::class)->find($articleId);

        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        if (!$article->isActive()) {
            throw new ArticleInactiveException($article);
        }

        $article = $articleService->update($article, $dto->name, $dto->amount);

        return $this->json([
            'article' => $this->articleSerializer->serialize($article),
        ]);
    }

    #[Route('/{articleId}', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Deactivate an article',
        description: 'Soft-delete — sets isActive=false and removes its barcodes.',
        tags: ['article'],
        parameters: [
            new OA\Parameter(name: 'articleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The deactivated article.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'article', ref: new Model(type: ArticleSchema::class)),
            ])),
            new OA\Response(response: 404, description: 'Error envelope (shape shared by all 4xx responses).', content: new OA\JsonContent(ref: new Model(type: ErrorSchema::class))),
        ],
    )]
    public function deleteArticle(string $articleId, EntityManagerInterface $entityManager): JsonResponse
    {
        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        foreach ($article->getBarcodes() as $barcode) {
            $entityManager->remove($barcode);
        }

        $article->setActive(false);

        $entityManager->flush();

        return $this->json([
            'article' => $this->articleSerializer->serialize($article),
        ]);
    }
}
