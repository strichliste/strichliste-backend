<?php

namespace App\Controller\Ui;

use App\Exception\AccountBalanceBoundaryException;
use App\Exception\ArticleInactiveException;
use App\Exception\ArticleNotFoundException;
use App\Exception\TransactionBoundaryException;
use App\Exception\TransactionInvalidException;
use App\Repository\ArticleRepository;
use App\Repository\BarcodeRepository;
use App\Repository\UserRepository;
use App\Service\TransactionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class BuyArticleController extends AbstractController {

    public function __construct(
        private UserRepository $userRepository,
        private ArticleRepository $articleRepository,
        private BarcodeRepository $barcodeRepository,
        private TransactionService $transactionService,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/user/{id}/transactions/buy', name: 'transactions_buy', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function buy(int $id, Request $request): Response {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new NotFoundHttpException();
        }

        if (!$this->isCsrfTokenValid('buy' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.generic'));
            return $this->redirectToRoute('users_detail', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        // The buy tab is hidden for disabled users, but hiding is not
        // enforcement — reject the POST itself.
        if ($user->isDisabled()) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.account_disabled'));
            return $this->redirectToRoute('users_detail', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $articleId = $request->request->get('articleId');
        $code = trim((string) $request->request->get('barcode', ''));

        $article = null;
        if ($articleId !== null && $articleId !== '') {
            $article = $this->articleRepository->findOneActive((int) $articleId);
        } elseif ($code !== '') {
            $barcode = $this->barcodeRepository->findByBarcode($code);
            if ($barcode) {
                $article = $barcode->getArticle();
            }
        }

        if (!$article) {
            $this->addFlash('error', $this->translator->trans('user.buy.errors.unknown_barcode', ['%barcode%' => $code]));
            return $this->redirectToRoute('users_detail', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        try {
            $this->transactionService->purchaseArticle($user, $article);
            $this->addFlash('success', $this->translator->trans('user.buy.success', [
                '%article%' => $article->getName(),
            ]));
            $this->addFlash('transaction_success', '1');
        } catch (TransactionBoundaryException | AccountBalanceBoundaryException | TransactionInvalidException $e) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.boundary'));
        } catch (ArticleInactiveException $e) {
            $this->addFlash('error', $this->translator->trans('articles.errors.inactive'));
        } catch (ArticleNotFoundException $e) {
            $this->addFlash('error', $this->translator->trans('articles.errors.not_found'));
        } catch (\Throwable $e) {
            $this->logger->error('Article purchase failed unexpectedly.', ['exception' => $e, 'user' => $user->getId()]);
            $this->addFlash('error', $this->translator->trans('transactions.errors.generic'));
        }

        return $this->redirectToRoute('users_detail', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
