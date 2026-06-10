<?php

namespace App\Controller\Ui;

use App\Exception\AccountBalanceBoundaryException;
use App\Exception\TransactionBoundaryException;
use App\Exception\TransactionInvalidException;
use App\Repository\UserRepository;
use App\Service\MoneyParser;
use App\Service\SettingsService;
use App\Service\TransactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PayPalController extends AbstractController {

    /**
     * Return URL is valid for 30 minutes after `start()` builds it. PayPal
     * usually redirects back within seconds; 30m covers payment-method-add
     * detours without leaving the URL exploitable indefinitely.
     */
    private const RETURN_URL_TTL = 1800;

    /** Session key holding the not-yet-consumed return nonces (nonce => cents). */
    private const PENDING_SESSION_KEY = 'paypal_pending';

    public function __construct(
        private UserRepository $userRepository,
        private SettingsService $settings,
        private TransactionService $transactionService,
        private TranslatorInterface $translator,
        private UriSigner $uriSigner,
        private MoneyParser $moneyParser,
    ) {
    }

    /**
     * Takes the operator's submitted euro amount, applies the configured fee,
     * builds the PayPal cgi-bin URL with major-unit `amount`, and redirects.
     * The return/cancel URLs are HMAC-signed with an expiration so they can't
     * be forged or replayed past the TTL.
     */
    #[Route('/user/{id}/paypal/start', name: 'paypal_start', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function start(int $id, Request $request): Response {
        if (!$this->settings->getOrDefault('paypal.enabled', false)) {
            throw new NotFoundHttpException();
        }

        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new NotFoundHttpException();
        }

        if (!$this->isCsrfTokenValid('paypal_start' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.generic'));
            return $this->redirectToRoute('users_detail', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        if ($user->isDisabled()) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.account_disabled'));
            return $this->redirectToRoute('users_detail', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $cents = $this->moneyParser->parseToCents($request->request->get('amount'));
        if ($cents === null || $cents <= 0) {
            $this->addFlash('error', $this->translator->trans('split_invoice.errors.invalid_amount'));
            return $this->redirectToRoute('users_detail', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        // Enforce the same boundaries createForUser() will apply on return —
        // BEFORE the member pays real money to the operator's PayPal account.
        // Otherwise a deposit above payment.boundary.upper (or one that pushes
        // the balance past account.boundary.upper) is paid but never credited.
        $paymentUpper = (int) $this->settings->getOrDefault('payment.boundary.upper', PHP_INT_MAX);
        $accountUpper = (int) $this->settings->getOrDefault('account.boundary.upper', PHP_INT_MAX);
        if ($cents > $paymentUpper || $user->getBalance() + $cents > $accountUpper) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.boundary'));
            return $this->redirectToRoute('users_detail', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $feePercent = (float) $this->settings->getOrDefault('paypal.fee', 0);
        $totalMajor = round(($cents / 100) * (1 + $feePercent / 100), 2);

        $sandbox = (bool) $this->settings->getOrDefault('paypal.sandbox', true);
        $base = $sandbox
            ? 'https://www.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://www.paypal.com/cgi-bin/webscr';

        // One-time nonce, stored server-side and consumed on the first
        // successful return. Combined with the HMAC signature this makes the
        // deposit idempotent: a replayed return URL (browser back/forward,
        // prefetch, link-preview bot) finds the nonce already consumed and
        // credits nothing.
        $nonce = bin2hex(random_bytes(16));
        $session = $request->getSession();
        $pending = $session->get(self::PENDING_SESSION_KEY, []);
        $pending[$nonce] = $cents;
        $session->set(self::PENDING_SESSION_KEY, $pending);

        // Build absolute return/cancel URLs and sign them. UriSigner adds
        // `_hash` and `_expiration` query params; `paypal_return_success`
        // and `paypal_return_cancel` verify both before doing any work.
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
            'item_name' => 'Strichliste · ' . $user->getName(),
            'currency_code' => (string) $this->settings->getOrDefault('i18n.currency.alpha3', 'EUR'),
            'amount' => number_format($totalMajor, 2, '.', ''),
            'rm' => '1',
            'no_shipping' => '1',
            'no_note' => '1',
            'return' => $returnSigned,
            'cancel_return' => $cancelSigned,
        ]);

        return new RedirectResponse($base . '?' . $query, Response::HTTP_SEE_OTHER);
    }

    #[Route('/user/{id}/paypal/return/{amount}', name: 'paypal_return_success', methods: ['GET'], requirements: ['id' => '\d+', 'amount' => '\d+'])]
    public function returnSuccess(int $id, int $amount, Request $request): Response {
        if (!$this->settings->getOrDefault('paypal.enabled', false)) {
            throw new NotFoundHttpException();
        }

        // Reject any request whose URL wasn't signed by `start()` or whose
        // signature has expired. Without this gate, a GET to this route
        // would deposit money on demand to anyone with the URL.
        if (!$this->uriSigner->checkRequest($request)) {
            throw new NotFoundHttpException();
        }

        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new NotFoundHttpException();
        }

        // Check the one-time nonce. A missing/unknown nonce means this signed
        // URL was already processed (or was never issued by start()) — treat it
        // as an idempotent replay and do not credit the account again.
        $nonce = (string) $request->query->get('nonce', '');
        $session = $request->getSession();
        $pending = $session->get(self::PENDING_SESSION_KEY, []);
        if ($nonce === '' || !array_key_exists($nonce, $pending)) {
            return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        try {
            $this->transactionService->createForUser($user, $amount, 'paypal');
            // Consume the nonce only once the credit is booked. The member has
            // already paid PayPal at this point — if crediting fails (boundary,
            // transient DB error), the signed URL must stay retryable within
            // its TTL instead of silently swallowing the payment. PHP's session
            // lock serializes concurrent requests on the same session, so this
            // cannot double-credit.
            unset($pending[$nonce]);
            $session->set(self::PENDING_SESSION_KEY, $pending);
            $this->addFlash('success', $this->translator->trans('paypal.return.success', [
                '%amount%' => $amount,
            ]));
            $this->addFlash('transaction_success', '1');
        } catch (TransactionBoundaryException | AccountBalanceBoundaryException | TransactionInvalidException $e) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.boundary'));
        } catch (\Throwable $e) {
            $this->addFlash('error', $this->translator->trans('paypal.return.error_body'));
        }

        return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/user/{id}/paypal/return/{amount}/error', name: 'paypal_return_cancel', methods: ['GET'], requirements: ['id' => '\d+', 'amount' => '\d+'])]
    public function returnCancel(int $id, int $amount, Request $request): Response {
        if (!$this->settings->getOrDefault('paypal.enabled', false)) {
            throw new NotFoundHttpException();
        }
        if (!$this->uriSigner->checkRequest($request)) {
            throw new NotFoundHttpException();
        }
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new NotFoundHttpException();
        }
        return $this->render('paypal/cancel.html.twig', ['user' => $user, 'amount' => $amount]);
    }
}
