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
    ) {
    }

    #[Route('/user/{id}/transactions/create', name: 'transactions_create', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function create(int $id, Request $request): Response {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new NotFoundHttpException();
        }

        $form = $this->createForm(CreateTransactionType::class, null, ['user_id' => $user->getId()]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.invalid'));
            return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        $data = $form->getData();
        $direction = $data['direction'] ?? 'deposit';
        // amount arrives as major units (float). Convert to cents, ignore sign,
        // then apply direction explicitly so a maliciously-signed input can't flip a deposit.
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

        $form = $this->createForm(TransferTransactionType::class, null, ['exclude_user' => $user]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.invalid'));
            return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        $data = $form->getData();
        $recipient = $data['recipient'];
        if ($recipient->getId() === $user->getId()) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.self_transfer'));
            return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }
        if ($recipient->isDisabled()) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.recipient_disabled'));
            return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
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
        } catch (TransactionBoundaryException | AccountBalanceBoundaryException | TransactionInvalidException $e) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.boundary'));
        } catch (UserNotFoundException $e) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.recipient_missing'));
        } catch (\Throwable $e) {
            $this->addFlash('error', $this->translator->trans('transactions.errors.generic'));
        }

        return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
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

        // The {id} segment is otherwise decorative for authorization: assert the
        // transaction actually belongs to this user so one user's undo URL can't
        // revert another user's transaction.
        $transaction = $this->transactionRepository->find($txId);
        if (!$transaction || $transaction->getUser()->getId() !== $user->getId()) {
            throw new NotFoundHttpException();
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
            $this->addFlash('error', $this->translator->trans('transactions.errors.generic'));
        }

        $return = $request->request->get('return');
        if ($return === 'history') {
            return $this->redirectToRoute('users_transactions', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }
        return $this->redirectToRoute('users_detail', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }
}
