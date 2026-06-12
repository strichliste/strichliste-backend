<?php

namespace App\Controller\Api;

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
    public function listBarcodes(EntityManagerInterface $entityManager): JsonResponse
    {
        $barcodes = $entityManager->getRepository(Barcode::class)->findBy([], ['created' => 'DESC']);

        return $this->json([
            'count' => count($barcodes),
            'barcodes' => array_map(fn (Barcode $barcode) => $this->barcodeSerializer->serialize($barcode), $barcodes),
        ]);
    }

    #[Route('/article/{articleId}/barcode', methods: ['GET'])]
    public function listArticleBarcode(int $articleId, EntityManagerInterface $entityManager): JsonResponse
    {
        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        $barcodes = $article->getBarcodes();

        return $this->json([
            'count' => count($barcodes),
            'barcodes' => array_map(fn (Barcode $barcode) => $this->barcodeSerializer->serialize($barcode), $barcodes),
        ]);
    }

    #[Route('/article/{articleId}/barcode/{barcodeId}', methods: ['GET'])]
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
