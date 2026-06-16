<?php

namespace App\Controller\Api;

use App\Dto\Api\AddBarcodeDto;
use App\Dto\Api\ArticleResponse;
use App\Dto\Api\BarcodeListResponse;
use App\Dto\Api\BarcodeResponse;
use App\Entity\Article;
use App\Entity\Barcode;
use App\Exception\ArticleBarcodeAlreadyExistsException;
use App\Exception\ArticleInactiveException;
use App\Exception\ArticleNotFoundException;
use App\Exception\BarcodeNotFoundException;
use App\Repository\BarcodeRepository;
use App\Serializer\ArticleSerializer;
use App\Serializer\BarcodeSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\Serialize;
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
            new OA\Response(response: 200, description: 'All barcodes.', content: new OA\JsonContent(ref: new Model(type: BarcodeListResponse::class))),
        ],
    )]
    #[Serialize]
    public function listBarcodes(EntityManagerInterface $entityManager): BarcodeListResponse
    {
        $barcodes = $entityManager->getRepository(Barcode::class)->findBy([], ['created' => 'DESC']);

        return new BarcodeListResponse(
            count: count($barcodes),
            barcodes: array_map($this->barcodeSerializer->serialize(...), $barcodes),
        );
    }

    #[Route('/article/{articleId}/barcode', methods: ['GET'])]
    #[OA\Get(
        summary: 'List an article\'s barcodes',
        tags: ['barcode'],
        parameters: [
            new OA\Parameter(name: 'articleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The article\'s barcodes.', content: new OA\JsonContent(ref: new Model(type: BarcodeListResponse::class))),
            new OA\Response(response: 404, ref: '#/components/responses/Error'),
        ],
    )]
    #[Serialize]
    public function listArticleBarcode(int $articleId, EntityManagerInterface $entityManager): BarcodeListResponse
    {
        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        $barcodes = $article->getBarcodes();

        return new BarcodeListResponse(
            count: count($barcodes),
            barcodes: array_map($this->barcodeSerializer->serialize(...), $barcodes),
        );
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
            new OA\Response(response: 200, description: 'The barcode.', content: new OA\JsonContent(ref: new Model(type: BarcodeResponse::class))),
            new OA\Response(response: 404, ref: '#/components/responses/Error'),
        ],
    )]
    #[Serialize]
    public function getArticleBarcode(int $articleId, int $barcodeId, EntityManagerInterface $entityManager): BarcodeResponse
    {
        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        $barcode = $entityManager->getRepository(Barcode::class)->find($barcodeId);
        if (!$barcode || $barcode->getArticle()->getId() !== $articleId) {
            throw new BarcodeNotFoundException($barcodeId);
        }

        return new BarcodeResponse($this->barcodeSerializer->serialize($barcode));
    }

    #[Route('/article/{articleId}/barcode', methods: ['POST'])]
    #[OA\Post(
        summary: 'Add a barcode to an article',
        tags: ['barcode'],
        parameters: [
            new OA\Parameter(name: 'articleId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: [
            new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(ref: new Model(type: AddBarcodeDto::class))),
            new OA\MediaType(mediaType: 'application/x-www-form-urlencoded', schema: new OA\Schema(ref: new Model(type: AddBarcodeDto::class))),
        ]),
        responses: [
            new OA\Response(response: 200, description: 'The article including the new barcode.', content: new OA\JsonContent(ref: new Model(type: ArticleResponse::class))),
            new OA\Response(response: 400, ref: '#/components/responses/Error'),
            new OA\Response(response: 404, ref: '#/components/responses/Error'),
            new OA\Response(response: 409, ref: '#/components/responses/Error'),
            new OA\Response(response: 422, ref: '#/components/responses/Error'),
        ],
    )]
    #[Serialize]
    public function addArticleBarcode(int $articleId, #[MapRequestPayload] AddBarcodeDto $dto, ArticleSerializer $articleSerializer, EntityManagerInterface $entityManager, BarcodeRepository $barcodeRepository): ArticleResponse
    {
        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        if (!$article->isActive()) {
            throw new ArticleInactiveException($article);
        }

        $existingBarcode = $barcodeRepository->findByBarcode($dto->barcode);
        if ($existingBarcode) {
            throw new ArticleBarcodeAlreadyExistsException($existingBarcode);
        }

        $newBarcode = new Barcode($dto->barcode);
        $article->addBarcode($newBarcode);

        $entityManager->persist($article);
        $entityManager->flush();

        return new ArticleResponse($articleSerializer->serialize($article));
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
            new OA\Response(response: 200, description: 'The article without the removed barcode.', content: new OA\JsonContent(ref: new Model(type: ArticleResponse::class))),
            new OA\Response(response: 404, ref: '#/components/responses/Error'),
        ],
    )]
    #[Serialize]
    public function deleteArticleBarcode(int $articleId, int $barcodeId, ArticleSerializer $articleSerializer, EntityManagerInterface $entityManager): ArticleResponse
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

        return new ArticleResponse($articleSerializer->serialize($article));
    }
}
