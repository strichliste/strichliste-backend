<?php

namespace App\Controller\Api;

use App\ApiDoc\Error as ErrorSchema;
use App\Entity\Article;
use App\Entity\Barcode;
use App\Exception\ArticleBarcodeAlreadyExistsException;
use App\Exception\ArticleInactiveException;
use App\Exception\ArticleNotFoundException;
use App\Exception\BarcodeNotFoundException;
use App\Exception\ParameterInvalidException;
use App\Repository\BarcodeRepository;
use App\Serializer\ArticleSerializer;
use App\Serializer\BarcodeSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class BarcodeController extends AbstractController
{
    public function __construct(private readonly BarcodeSerializer $barcodeSerializer)
    {
    }

    #[Route('/barcode', methods: ['GET'])]
    #[OA\Get(
        summary: 'List all barcodes',
        tags: ['barcode'],
        responses: [
            new OA\Response(response: 200, description: 'All barcodes.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'count', type: 'integer'),
                new OA\Property(property: 'barcodes', type: 'array', items: new OA\Items(ref: '#/components/schemas/Barcode')),
            ])),
        ],
    )]
    public function listBarcodes(EntityManagerInterface $entityManager): JsonResponse
    {
        $barcodes = $entityManager->getRepository(Barcode::class)->findBy([], ['created' => 'DESC']);

        return $this->json([
            'count' => count($barcodes),
            'barcodes' => array_map($this->barcodeSerializer->serialize(...), $barcodes),
        ]);
    }

    #[Route('/article/{articleId}/barcode', methods: ['GET'])]
    #[OA\Get(
        summary: 'List an article\'s barcodes',
        tags: ['barcode'],
        parameters: [
            new OA\Parameter(name: 'articleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The article\'s barcodes.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'count', type: 'integer'),
                new OA\Property(property: 'barcodes', type: 'array', items: new OA\Items(ref: '#/components/schemas/Barcode')),
            ])),
            new OA\Response(response: 404, description: 'Error envelope (shape shared by all 4xx responses).', content: new OA\JsonContent(ref: new Model(type: ErrorSchema::class))),
        ],
    )]
    public function listArticleBarcode(int $articleId, EntityManagerInterface $entityManager): JsonResponse
    {
        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        $barcodes = $article->getBarcodes();

        return $this->json([
            'count' => count($barcodes),
            'barcodes' => array_map($this->barcodeSerializer->serialize(...), $barcodes),
        ]);
    }

    #[Route('/article/{articleId}/barcode/{barcodeId}', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a single barcode',
        tags: ['barcode'],
        parameters: [
            new OA\Parameter(name: 'articleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'barcodeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The barcode.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'barcode', ref: '#/components/schemas/Barcode'),
            ])),
            new OA\Response(response: 404, description: 'Error envelope (shape shared by all 4xx responses).', content: new OA\JsonContent(ref: new Model(type: ErrorSchema::class))),
        ],
    )]
    public function getArticleBarcode(int $articleId, int $barcodeId, EntityManagerInterface $entityManager): JsonResponse
    {
        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        $barcode = $entityManager->getRepository(Barcode::class)->find($barcodeId);
        if (!$barcode || $barcode->getArticle()->getId() !== $articleId) {
            throw new BarcodeNotFoundException($barcodeId);
        }

        return $this->json([
            'barcode' => $this->barcodeSerializer->serialize($barcode),
        ]);
    }

    #[Route('/article/{articleId}/barcode', methods: ['POST'])]
    #[OA\Post(
        summary: 'Add a barcode to an article',
        tags: ['barcode'],
        parameters: [
            new OA\Parameter(name: 'articleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['barcode'],
            properties: [
                new OA\Property(property: 'barcode', type: 'string'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'The article including the new barcode.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'article', ref: '#/components/schemas/Article'),
            ])),
            new OA\Response(response: 400, description: 'Error envelope (shape shared by all 4xx responses).', content: new OA\JsonContent(ref: new Model(type: ErrorSchema::class))),
            new OA\Response(response: 404, description: 'Error envelope (shape shared by all 4xx responses).', content: new OA\JsonContent(ref: new Model(type: ErrorSchema::class))),
            new OA\Response(response: 409, description: 'Error envelope (shape shared by all 4xx responses).', content: new OA\JsonContent(ref: new Model(type: ErrorSchema::class))),
        ],
    )]
    public function addArticleBarcode(int $articleId, Request $request, ArticleSerializer $articleSerializer, EntityManagerInterface $entityManager, BarcodeRepository $barcodeRepository): JsonResponse
    {
        $barcode = trim($request->request->getString('barcode'));
        if (!$barcode) {
            throw new ParameterInvalidException('barcode');
        }

        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        if (!$article->isActive()) {
            throw new ArticleInactiveException($article);
        }

        $existingBarcode = $barcodeRepository->findByBarcode($barcode);
        if ($existingBarcode) {
            throw new ArticleBarcodeAlreadyExistsException($existingBarcode);
        }

        $newBarcode = new Barcode($barcode);
        $article->addBarcode($newBarcode);

        $entityManager->persist($article);
        $entityManager->flush();

        return $this->json([
            'article' => $articleSerializer->serialize($article),
        ]);
    }

    #[Route('/article/{articleId}/barcode/{barcodeId}', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Remove a barcode from an article',
        tags: ['barcode'],
        parameters: [
            new OA\Parameter(name: 'articleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'barcodeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The article without the removed barcode.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'article', ref: '#/components/schemas/Article'),
            ])),
            new OA\Response(response: 404, description: 'Error envelope (shape shared by all 4xx responses).', content: new OA\JsonContent(ref: new Model(type: ErrorSchema::class))),
        ],
    )]
    public function deleteArticleBarcode(int $articleId, int $barcodeId, ArticleSerializer $articleSerializer, EntityManagerInterface $entityManager): JsonResponse
    {
        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        $existingBarcode = $entityManager->getRepository(Barcode::class)->find($barcodeId);
        if (!$existingBarcode || $existingBarcode->getArticle()->getId() !== $articleId) {
            throw new BarcodeNotFoundException($barcodeId);
        }

        $entityManager->remove($existingBarcode);
        $entityManager->flush();

        return $this->json([
            'article' => $articleSerializer->serialize($article),
        ]);
    }
}
