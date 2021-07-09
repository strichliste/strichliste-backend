<?php

namespace App\Controller\Api;

use App\Entity\Article;
use App\Entity\Barcode;
use App\Exception\ArticleBarcodeAlreadyExistsException;
use App\Exception\ArticleNotFoundException;
use App\Exception\BarcodeInvalidException;
use App\Exception\BarcodeNotFoundException;
use App\Serializer\ArticleSerializer;
use App\Serializer\BarcodeSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api")
 */
class BarcodeController extends AbstractController {

    private $barcodeSerializer;

    function __construct(BarcodeSerializer $barcodeSerializer) {
        $this->barcodeSerializer = $barcodeSerializer;
    }

    /**
     * @Route("/barcode", methods="GET")
     */
    function listBarcodes(EntityManagerInterface $entityManager) {
        $barcodes = $entityManager->getRepository(Barcode::class)->findBy([], ['created' => 'DESC']);

        return $this->json([
            'count' => count($barcodes),
            'barcodes' => array_map(function (Barcode $barcode) {
                return $this->barcodeSerializer->serialize($barcode);
            }, $barcodes),
        ]);
    }

    /**
     * @Route("/article/{articleId}/barcode", methods="GET")
     */
    function listArticleBarcode(int $articleId, EntityManagerInterface $entityManager) {
        $article = $entityManager->getRepository(Article::class)->find($articleId);
        $barcodes = $article->getBarcodes()->getValues();

        return $this->json([
            'count' => count($barcodes),
            'barcodes' => array_map(function (Barcode $barcode) {
                return $this->barcodeSerializer->serialize($barcode);
            }, $barcodes),
        ]);
    }

    /**
     * @Route("/article/{articleId}/barcode/{barcodeId}", methods="GET")
     */
    function getArticleBarcode(int $articleId, int $barcodeId, EntityManagerInterface $entityManager) {
        $barcode = $entityManager->getRepository(Barcode::class)->find($barcodeId);
        if (!$barcode) {
            throw new BarcodeNotFoundException($barcodeId);
        }

        return $this->json([
            'barcode' => $this->barcodeSerializer->serialize($barcode)
        ]);
    }

    /**
     * @Route("/article/{articleId}/barcode", methods="POST")
     */
    function addArticleBarcode(int $articleId, Request $request, ArticleSerializer $articleSerializer, EntityManagerInterface $entityManager) {
        $barcode = $request->request->get('barcode');

        if (!$barcode) {
            throw new BarcodeInvalidException($barcode);
        }

        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        $existingBarcode = $entityManager->getRepository(Barcode::class)->findByBarcode($barcode);
        if ($existingBarcode) {
            throw new ArticleBarcodeAlreadyExistsException($existingBarcode);
        }

        $newBarcode = new Barcode($barcode);
        $article->addBarcode($newBarcode);

        $entityManager->persist($article);
        $entityManager->flush();

        return $this->json([
            'article' => $articleSerializer->serialize($article)
        ]);
    }

    /**
     * @Route("/article/{articleId}/barcode/{barcodeId}", methods="DELETE")
     */
    function deleteArticleBarcode(int $articleId, int $barcodeId, ArticleSerializer $articleSerializer, EntityManagerInterface $entityManager) {
        $article = $entityManager->getRepository(Article::class)->find($articleId);
        if (!$article) {
            throw new ArticleNotFoundException($articleId);
        }

        $existingBarcode = $entityManager->getRepository(Barcode::class)->find($barcodeId);
        if (!$existingBarcode) {
            throw new BarcodeNotFoundException($barcodeId);
        }

        $entityManager->remove($existingBarcode);
        $entityManager->flush();

        return $this->json([
            'article' => $articleSerializer->serialize($article)
        ]);
    }
}
