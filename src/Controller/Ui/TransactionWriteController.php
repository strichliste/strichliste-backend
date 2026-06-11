<?php

namespace App\Controller\Ui;

use App\Exception\AccountBalanceBoundaryException;
use App\Exception\TransactionBoundaryException;
use App\Exception\TransactionInvalidException;
use App\Exception\TransactionNotDeletableException;
use App\Exception\TransactionNotFoundException;
use App\Exception\UserNotFoundException;
use App\Form\CreateTransactionType;
use App\Form\TransferTransactionType;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Service\MoneyParser;
use App\Service\TransactionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class TransactionWriteController extends AbstractController {

    public function __construct(
        private UserRepository $userRepository,
        private TransactionService $transactionService,
        private TransactionRepository $transactionRepository,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/user/{id}/transactions/create', name: 'transactions_create', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function create(int $id, Request $request): Response {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new NotFoundHttpException();
        }

        // the forms are hidden for disabled users, but enforce it on the POST too
        if ($user->isDisabled()) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.account_disabled'));
            return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        $form = $this->createForm(CreateTransactionType::class, null, ['user_id' => $user->getId()]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.invalid'));
            return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        $data = $form->getData();
        $direction = $data['direction'] ?? 'deposit';
        // ignore the submitted sign and apply direction explicitly so a crafted negative can't flip a deposit
        $cents = MoneyParser::majorToCents(abs((float) $data['amount']));
        $amount = $direction === 'dispense' ? -$cents : $cents;
        if ($amount === 0) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.invalid'));
            return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }
        $comment = $data['comment'] ?: null;

        try {
            $tx = $this->transactionService->createForUser($user, $amount, $comment);
            $this->addFlash('success', $this->translator->trans(
                $direction === 'dispense' ? 'transactions.flash.dispense_success' : 'transactions.flash.deposit_success'
            ));
            $this->addFlash('transaction_success', '1');
        } catch (TransactionBoundaryException | AccountBalanceBoundaryException | TransactionInvalidException $e) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.boundary'));
        } catch (\Throwable $e) {
            $this->logger->error('Transaction create failed unexpectedly.', ['exception' => $e, 'user' => $user->getId()]);
            $this->addFlash('error', $this->translator->trans('transactions.errors.generic'));
        }

        return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/user/{id}/transactions/transfer', name: 'transactions_transfer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function transfer(int $id, Request $request): Response {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new NotFoundHttpException();
        }

        if ($user->isDisabled()) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.account_disabled'));
            return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        $form = $this->createForm(TransferTransactionType::class, null, ['exclude_user' => $user]);
        $form->handleRequest($request);

        // reopen the send tab on errors so the form doesn't vanish behind a flash
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.invalid'));
            return $this->redirectToRoute('users_detail', ['id' => $user->getId(), 'tab' => 'send'], Response::HTTP_SEE_OTHER);
        }

        $data = $form->getData();
        $recipient = $data['recipient'];
        if ($recipient->getId() === $user->getId()) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.self_transfer'));
            return $this->redirectToRoute('users_detail', ['id' => $user->getId(), 'tab' => 'send'], Response::HTTP_SEE_OTHER);
        }
        if ($recipient->isDisabled()) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.recipient_disabled'));
            return $this->redirectToRoute('users_detail', ['id' => $user->getId(), 'tab' => 'send'], Response::HTTP_SEE_OTHER);
        }

        // amount is major units; transfer always debits the current user.
        $cents = MoneyParser::majorToCents(abs((float) $data['amount']));
        $amount = -$cents;
        $comment = $data['comment'] ?: null;

        try {
            $this->transactionService->transferBetween($user, $recipient, $amount, $comment);
            $this->addFlash('success', $this->translator->trans('transactions.flash.transfer_success', [
                '%recipient%' => $recipient->getName(),
            ]));
            $this->addFlash('transaction_success', '1');
            return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        } catch (TransactionBoundaryException | AccountBalanceBoundaryException | TransactionInvalidException $e) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.boundary'));
        } catch (UserNotFoundException $e) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.recipient_missing'));
        } catch (\Throwable $e) {
            $this->logger->error('Transfer failed unexpectedly.', ['exception' => $e, 'user' => $user->getId()]);
            $this->addFlash('error', $this->translator->trans('transactions.errors.generic'));
        }

        return $this->redirectToRoute('users_detail', ['id' => $user->getId(), 'tab' => 'send'], Response::HTTP_SEE_OTHER);
    }

    #[Route('/user/{id}/transactions/{txId}/undo', name: 'transactions_undo', methods: ['POST'], requirements: ['id' => '\d+', 'txId' => '\d+'])]
    public function undo(int $id, int $txId, Request $request): Response {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new NotFoundHttpException();
        }

        if (!$this->isCsrfTokenValid('undo' . $user->getId() . '_' . $txId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.generic'));
            return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        // make sure the transaction belongs to this user — the {id} segment alone authorizes nothing
        $transaction = $this->transactionRepository->find($txId);
        if (!$transaction || $transaction->getUser()->getId() !== $user->getId()) {
            throw new NotFoundHttpException();
        }

        // a stale tab keeps a valid CSRF token forever — enforce the undo window on the POST too
        if (!$this->transactionService->isDeletable($transaction)) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.not_deletable'));
            return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        try {
            $this->transactionService->revertTransaction($txId);
            $this->addFlash('success', $this->translator->trans('transactions.flash.undo_success'));
            $this->addFlash('transaction_success', '1');
        } catch (TransactionNotFoundException $e) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.generic'));
        } catch (TransactionNotDeletableException $e) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.not_deletable'));
        } catch (\Throwable $e) {
            $this->logger->error('Undo failed unexpectedly.', ['exception' => $e, 'user' => $user->getId(), 'tx' => $txId]);
            $this->addFlash('error', $this->translator->trans('transactions.errors.generic'));
        }

        $return = $request->request->get('return');
        if ($return === 'history') {
            return $this->redirectToRoute('users_transactions', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }
        return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }
}
