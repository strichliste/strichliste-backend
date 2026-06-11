<?php

namespace App\Controller\Ui;

use App\Entity\Article;
use App\Entity\Barcode;
use App\Repository\BarcodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ArticleBarcodeController extends AbstractController {

    public function __construct(
        private BarcodeRepository $barcodeRepository,
        private EntityManagerInterface $em,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('/articles/{id}/barcodes', name: 'articles_barcodes_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function add(Article $article, Request $request): Response {
        if (!$this->isCsrfTokenValid('add_barcode' . $article->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.generic'));
            return $this->redirectToRoute('articles_edit', ['id' => $article->getId()], Response::HTTP_SEE_OTHER);
        }

        $code = trim((string) $request->request->get('barcode', ''));
        if ($code === '' || mb_strlen($code) > 32) {
            $this->addFlash('error', $this->translator->trans('articles.barcodes.errors.invalid'));
            return $this->redirectToRoute('articles_edit', ['id' => $article->getId()], Response::HTTP_SEE_OTHER);
        }

        $existing = $this->barcodeRepository->findByBarcode($code);
        if ($existing) {
            $this->addFlash('error', $this->translator->trans('articles.barcodes.errors.taken', [
                '%other%' => $existing->getArticle()->getName(),
            ]));
            return $this->redirectToRoute('articles_edit', ['id' => $article->getId()], Response::HTTP_SEE_OTHER);
        }

        $barcode = new Barcode($code);
        $barcode->setArticle($article);
        $this->em->persist($barcode);
        $this->em->flush();

        $this->addFlash('success', $this->translator->trans('articles.barcodes.added'));
        return $this->redirectToRoute('articles_edit', ['id' => $article->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/articles/{id}/barcodes/{bid}/delete', name: 'articles_barcodes_delete', methods: ['POST'], requirements: ['id' => '\d+', 'bid' => '\d+'])]
    public function delete(Article $article, int $bid, Request $request): Response {
        if (!$this->isCsrfTokenValid('delete_barcode' . $bid, (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('articles_edit', ['id' => $article->getId()], Response::HTTP_SEE_OTHER);
        }

        $barcode = $this->barcodeRepository->find($bid);
        if ($barcode && $barcode->getArticle()->getId() === $article->getId()) {
            $this->em->remove($barcode);
            $this->em->flush();
            $this->addFlash('success', $this->translator->trans('articles.barcodes.removed'));
        }

        return $this->redirectToRoute('articles_edit', ['id' => $article->getId()], Response::HTTP_SEE_OTHER);
    }
}
