<?php

namespace App\Controller\Ui;

use App\Entity\User;
use App\Exception\AccountBalanceBoundaryException;
use App\Exception\TransactionBoundaryException;
use App\Exception\TransactionInvalidException;
use App\Service\MoneyParser;
use App\Service\SettingsService;
use App\Service\TransactionService;
use App\Twig\AppExtension;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PayPalController extends AbstractController
{
    // 30m covers payment-method detours without leaving the signed return URL live forever
    private const int RETURN_URL_TTL = 1800;

    // not-yet-consumed return nonces (nonce => cents)
    private const string PENDING_SESSION_KEY = 'paypal_pending';

    public function __construct(
        private readonly SettingsService $settings,
        private readonly TransactionService $transactionService,
        private readonly TranslatorInterface $translator,
        private readonly UriSigner $uriSigner,
        private readonly MoneyParser $moneyParser,
        private readonly AppExtension $appExtension,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/user/{id}/paypal/start', name: 'paypal_start', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function start(User $user, Request $request): Response
    {
        if (!$this->settings->getOrDefault('paypal.enabled', false)) {
            throw new NotFoundHttpException();
        }

        if (!$this->isCsrfTokenValid('paypal_start'.$user->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.generic'));

            return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        if ($user->isDisabled()) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.account_disabled'));

            return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        $cents = $this->moneyParser->parseToCents($request->request->getString('amount'));
        if (null === $cents || $cents <= 0) {
            $this->addFlash('error', $this->translator->trans('split_invoice.errors.invalid_amount'));

            return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        // check the boundaries before the member pays PayPal, or the deposit is paid but never credited
        $paymentUpper = (int) $this->settings->getOrDefault('payment.boundary.upper', PHP_INT_MAX);
        $accountUpper = (int) $this->settings->getOrDefault('account.boundary.upper', PHP_INT_MAX);
        if ($cents > $paymentUpper || $user->getBalance() + $cents > $accountUpper) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.boundary'));

            return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        $feePercent = (float) $this->settings->getOrDefault('paypal.fee', 0);
        $totalMajor = round(($cents / 100) * (1 + $feePercent / 100), 2);

        $sandbox = (bool) $this->settings->getOrDefault('paypal.sandbox', true);
        $base = $sandbox
            ? 'https://www.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://www.paypal.com/cgi-bin/webscr';

        // one-time nonce makes the return idempotent: a replayed URL finds it consumed and credits nothing
        $nonce = bin2hex(random_bytes(16));
        $session = $request->getSession();
        $pending = $session->get(self::PENDING_SESSION_KEY, []);
        $pending[$nonce] = $cents;
        $session->set(self::PENDING_SESSION_KEY, $pending);

        $returnUnsigned = $this->generateUrl(
            'paypal_return_success',
            ['id' => $user->getId(), 'amount' => $cents, 'nonce' => $nonce],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $cancelUnsigned = $this->generateUrl(
            'paypal_return_cancel',
            ['id' => $user->getId(), 'amount' => $cents],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $expiresAt = time() + self::RETURN_URL_TTL;
        $returnSigned = $this->uriSigner->sign($returnUnsigned, $expiresAt);
        $cancelSigned = $this->uriSigner->sign($cancelUnsigned, $expiresAt);

        $query = http_build_query([
            'cmd' => '_xclick',
            'business' => (string) $this->settings->getOrDefault('paypal.recipient', ''),
            'item_name' => 'Strichliste · '.$user->getName(),
            'currency_code' => (string) $this->settings->getOrDefault('i18n.currency.alpha3', 'EUR'),
            'amount' => number_format($totalMajor, 2, '.', ''),
            'rm' => '1',
            'no_shipping' => '1',
            'no_note' => '1',
            'return' => $returnSigned,
            'cancel_return' => $cancelSigned,
        ]);

        return new RedirectResponse($base.'?'.$query, Response::HTTP_SEE_OTHER);
    }

    #[Route('/user/{id}/paypal/return/{amount}', name: 'paypal_return_success', methods: ['GET'], requirements: ['id' => '\d+', 'amount' => '\d+'])]
    public function returnSuccess(User $user, int $amount, Request $request): Response
    {
        if (!$this->settings->getOrDefault('paypal.enabled', false)) {
            throw new NotFoundHttpException();
        }

        // unsigned or expired URLs would let anyone with the link deposit money on demand
        if (!$this->uriSigner->checkRequest($request)) {
            throw new NotFoundHttpException();
        }

        // unknown nonce = already processed or never issued; treat as replay, don't credit again
        $nonce = $request->query->getString('nonce');
        $session = $request->getSession();
        $pending = $session->get(self::PENDING_SESSION_KEY, []);
        if ('' === $nonce || !array_key_exists($nonce, $pending)) {
            return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        try {
            $this->transactionService->createForUser($user, $amount, 'paypal');
            // consume only after the credit books so failures stay retryable; the session lock prevents double-credit
            unset($pending[$nonce]);
            $session->set(self::PENDING_SESSION_KEY, $pending);
            $this->addFlash('success', $this->translator->trans('paypal.return.success', [
                '%amount%' => $this->appExtension->currencyFormat($amount, null, false),
            ]));
            $this->addFlash('transaction_success', '1');
        } catch (TransactionBoundaryException|AccountBalanceBoundaryException|TransactionInvalidException) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.boundary'));
        } catch (\Throwable $e) {
            // the member has already paid at this point — never fail silently
            $this->logger->critical('PayPal deposit could not be credited after payment.', ['exception' => $e, 'user' => $user->getId(), 'cents' => $amount]);
            $this->addFlash('error', $this->translator->trans('paypal.return.error_body'));
        }

        return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/user/{id}/paypal/return/{amount}/error', name: 'paypal_return_cancel', methods: ['GET'], requirements: ['id' => '\d+', 'amount' => '\d+'])]
    public function returnCancel(User $user, int $amount, Request $request): Response
    {
        if (!$this->settings->getOrDefault('paypal.enabled', false)) {
            throw new NotFoundHttpException();
        }
        if (!$this->uriSigner->checkRequest($request)) {
            throw new NotFoundHttpException();
        }

        return $this->render('paypal/cancel.html.twig', ['user' => $user]);
    }
}
