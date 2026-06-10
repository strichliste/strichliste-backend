<?php

namespace App\Controller\Ui;

use App\Entity\User;
use App\Form\CreateTransactionType;
use App\Form\CreateUserType;
use App\Form\EditUserType;
use App\Form\TransferTransactionType;
use App\Repository\ArticleRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Service\SettingsService;
use App\Service\TransactionService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserController extends AbstractController {

    private const TABS = ['send', 'buy', 'edit', 'paypal'];

    public function __construct(
        private UserRepository $userRepository,
        private TransactionRepository $transactionRepository,
        private SettingsService $settings,
        private EntityManagerInterface $em,
        private TransactionService $transactionService,
        private ArticleRepository $articleRepository,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('/user/{id}', name: 'users_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id, Request $request): Response {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new NotFoundHttpException();
        }

        // Default state has NO tab selected — recent transactions are the page
        // body. A tab is only active when `?tab=` is explicitly set to a valid
        // value. `article.autoOpen` still forces the buy tab on first arrival
        // (no `tab` query param at all).
        $tab = $request->query->get('tab');
        if ($tab !== null && !in_array($tab, self::TABS, true)) {
            $tab = null;
        }
        if ($tab === null && !$request->query->has('tab')
            && $this->settings->getOrDefault('article.autoOpen', false)) {
            $tab = 'buy';
        }

        $recent = $this->transactionRepository->findByUser($user, 5, 0);
        $totalTx = $this->transactionRepository->countByUser($user);

        $editForm = null;
        if ($tab === 'edit') {
            $editForm = $this->createForm(EditUserType::class, [
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'isDisabled' => $user->isDisabled(),
            ])->createView();
        }

        // Step buttons are always shown; the transfer form is only built when
        // the SEND tab is active (avoids an unnecessary EntityType SELECT and
        // CSRF token on every detail load).
        $sendData = $this->prepareSendTab($user, $tab === 'send');
        $buyData = $tab === 'buy' ? [
            'articles' => $this->articleRepository->findBy(['active' => true], ['name' => 'ASC'], 50),
        ] : null;

        // mark each row for the recent list whether it's deletable per the live service check
        $recentMeta = array_map(fn($tx) => [
            'tx' => $tx,
            'deletable' => $this->transactionService->isDeletable($tx),
        ], $recent);

        return $this->render('users/detail.html.twig', [
            'user' => $user,
            'activeTab' => $tab,
            'recentTransactions' => $recentMeta,
            'totalTransactions' => $totalTx,
            'currencySymbol' => $this->settings->getOrDefault('i18n.currency.symbol', '€'),
            'editForm' => $editForm,
            'showSendTab' => $this->settings->getOrDefault('payment.transactions.enabled', true) && !$user->isDisabled(),
            'showBuyTab' => $this->settings->getOrDefault('article.enabled', true) && !$user->isDisabled(),
            'showPaypalTab' => $this->settings->getOrDefault('paypal.enabled', false) && !$user->isDisabled(),
            'send' => $sendData,
            'buy' => $buyData,
        ]);
    }

    /**
     * Build the step-button + transfer-form view-data.
     *
     * @param bool $includeTransferForm only true when the SEND tab is active —
     *        avoids an EntityType SELECT on every detail page load.
     */
    private function prepareSendTab(User $user, bool $includeTransferForm = false): array {
        $transferForm = $includeTransferForm
            ? $this->createForm(TransferTransactionType::class, null, ['exclude_user' => $user])->createView()
            : null;

        // Step buttons are hidden for disabled users (they shouldn't transact).
        if ($user->isDisabled() || !$this->settings->getOrDefault('payment.transactions.enabled', true)) {
            return [
                'deposit_enabled' => false, 'deposit_custom' => false, 'deposit_steps' => [],
                'dispense_enabled' => false, 'dispense_custom' => false, 'dispense_steps' => [],
                'deposit_form' => $this->createForm(CreateTransactionType::class, ['direction' => 'deposit'], ['user_id' => $user->getId()])->createView(),
                'dispense_form' => $this->createForm(CreateTransactionType::class, ['direction' => 'dispense'], ['user_id' => $user->getId()])->createView(),
                'transfer_form' => $transferForm,
            ];
        }

        $accountLower = (int) $this->settings->getOrDefault('account.boundary.lower', PHP_INT_MIN);
        $accountUpper = (int) $this->settings->getOrDefault('account.boundary.upper', PHP_INT_MAX);
        $paymentLower = (int) $this->settings->getOrDefault('payment.boundary.lower', PHP_INT_MIN);
        $paymentUpper = (int) $this->settings->getOrDefault('payment.boundary.upper', PHP_INT_MAX);
        $balance = (int) $user->getBalance();

        $stepDisabled = function (int $signedAmount) use ($balance, $accountLower, $accountUpper, $paymentLower, $paymentUpper): bool {
            if ($signedAmount > $paymentUpper || $signedAmount < $paymentLower) return true;
            $resulting = $balance + $signedAmount;
            return $resulting > $accountUpper || $resulting < $accountLower;
        };

        $depositSteps = array_map(
            fn(int $cents) => ['amount' => $cents, 'disabled' => $stepDisabled($cents)],
            (array) $this->settings->getOrDefault('payment.deposit.steps', [])
        );
        $dispenseSteps = array_map(
            fn(int $cents) => ['amount' => -$cents, 'disabled' => $stepDisabled(-$cents)],
            (array) $this->settings->getOrDefault('payment.dispense.steps', [])
        );

        // direction is pre-bound so the template doesn't have to inject a
        // duplicate hidden field — form_end renders the HiddenType once.
        $depositForm = $this->createForm(CreateTransactionType::class, ['direction' => 'deposit'], ['user_id' => $user->getId()])->createView();
        $dispenseForm = $this->createForm(CreateTransactionType::class, ['direction' => 'dispense'], ['user_id' => $user->getId()])->createView();

        return [
            'deposit_enabled' => (bool) $this->settings->getOrDefault('payment.deposit.enabled', true),
            'deposit_custom' => (bool) $this->settings->getOrDefault('payment.deposit.custom', true),
            'deposit_steps' => $depositSteps,
            'dispense_enabled' => (bool) $this->settings->getOrDefault('payment.dispense.enabled', true),
            'dispense_custom' => (bool) $this->settings->getOrDefault('payment.dispense.custom', true),
            'dispense_steps' => $dispenseSteps,
            'deposit_form' => $depositForm,
            'dispense_form' => $dispenseForm,
            'transfer_form' => $transferForm,
        ];
    }

    #[Route('/user/active/add', name: 'users_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response {
        $form = $this->createForm(CreateUserType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $name = User::sanitizeName($data['name']);

            $existing = $this->userRepository->findByName($name);
            if ($existing) {
                $form->get('name')->addError(new FormError($this->translator->trans('user.create.errors.duplicate')));
            } else {
                $user = (new User())->setName($name);
                $this->em->persist($user);
                try {
                    $this->em->flush();
                    $this->addFlash('success', $this->translator->trans('user.create.success', ['%name%' => $user->getName()]));
                    return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
                } catch (UniqueConstraintViolationException $e) {
                    // Name was taken between the findByName() check and flush()
                    // (concurrent create). Surface the same friendly error.
                    $form->get('name')->addError(new FormError($this->translator->trans('user.create.errors.duplicate')));
                }
            }
        }

        // 422 on failed submits — Turbo ignores non-redirect form responses
        // that come back 200, so errors would never reach the screen.
        return $this->render('users/create.html.twig', [
            'form' => $form->createView(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/user/{id}/edit', name: 'users_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new NotFoundHttpException();
        }

        $form = $this->createForm(EditUserType::class, [
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'isDisabled' => $user->isDisabled(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $name = User::sanitizeName($data['name']);

            $existing = $this->userRepository->findByName($name);
            if ($existing && $existing->getId() !== $user->getId()) {
                $form->get('name')->addError(new FormError($this->translator->trans('user.create.errors.duplicate')));
            } else {
                $user->setName($name);
                $user->setEmail($data['email'] ? trim($data['email']) : null);
                $wasDisabled = $user->isDisabled();
                $user->setDisabled((bool) $data['isDisabled']);
                $this->em->persist($user);
                try {
                    $this->em->flush();

                    if (!$wasDisabled && $user->isDisabled()) {
                        $this->addFlash('success', $this->translator->trans('user.edit.disabled_success', ['%name%' => $user->getName()]));
                        return $this->redirectToRoute('users_active', [], Response::HTTP_SEE_OTHER);
                    }

                    $this->addFlash('success', $this->translator->trans('user.edit.success'));
                    return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
                } catch (UniqueConstraintViolationException $e) {
                    // Name was taken between the findByName() check and flush().
                    $form->get('name')->addError(new FormError($this->translator->trans('user.create.errors.duplicate')));
                }
            }
        }

        return $this->render('users/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

}
