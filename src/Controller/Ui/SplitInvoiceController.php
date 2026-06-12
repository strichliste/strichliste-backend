<?php

namespace App\Controller\Ui;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\MoneyParser;
use App\Service\SettingsService;
use App\Service\TransactionService;
use App\Twig\AppExtension;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class SplitInvoiceController extends AbstractController
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly UserRepository $userRepository,
        private readonly TransactionService $transactionService,
        private readonly TranslatorInterface $translator,
        private readonly MoneyParser $moneyParser,
        private readonly AppExtension $appExtension,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/split-invoice', name: 'split_invoice_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if (!$this->settings->getOrDefault('payment.splitInvoice.enabled', false)) {
            throw new NotFoundHttpException();
        }

        $allUsers = $this->userRepository->findAll(); // excludes disabled

        $errors = [];
        $rowErrors = [];
        $formData = [
            'recipient' => null,
            'amount_major' => null,
            'comment' => null,
            'participants' => [],
        ];

        // by reference: the closure must see values filled in during POST handling.
        // Always render at least one participant row so the form works without JS.
        $renderArgs = function () use ($allUsers, &$formData, &$errors, &$rowErrors) {
            $formData['participants'] = $formData['participants'] ?: [null];

            return [
                'users' => $allUsers, 'formData' => $formData, 'errors' => $errors, 'rowErrors' => $rowErrors,
            ];
        };

        // error re-renders are 422 or Turbo won't render them
        $renderError = fn () => $this->render('split_invoice/index.html.twig', $renderArgs(),
            new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));

        if (!$request->isMethod('POST')) {
            return $this->render('split_invoice/index.html.twig', $renderArgs());
        }

        if (!$this->isCsrfTokenValid('split_invoice', $request->request->getString('_token'))) {
            $errors[] = $this->translator->trans('transactions.errors.generic');

            return $renderError();
        }

        $recipientId = (int) $request->request->get('recipient', 0);
        $amountCents = $this->moneyParser->parseToCents($request->request->get('amount'));
        $comment = trim((string) $request->request->get('comment', '')) ?: null;
        $participantIds = array_map(intval(...), (array) $request->request->all('participants'));

        $formData['recipient'] = $recipientId;
        $formData['amount_major'] = $request->request->get('amount');
        $formData['comment'] = $comment;
        $formData['participants'] = $participantIds;

        // no-JS path of the add-participant button: append a row and re-render without validating
        if ($request->request->has('add_row')) {
            $formData['participants'][] = null;

            return $renderError();
        }

        $recipient = $recipientId > 0 ? $this->userRepository->find($recipientId) : null;
        if (!$recipient || $recipient->isDisabled()) {
            $errors[] = $this->translator->trans('split_invoice.errors.recipient_missing');
        }

        if (null === $amountCents || $amountCents <= 0) {
            $errors[] = $this->translator->trans('split_invoice.errors.invalid_amount');
        }

        $cleanParticipants = $this->resolveParticipants($participantIds, $rowErrors);

        if (0 === count($cleanParticipants) && !$errors && !$rowErrors) {
            $errors[] = $this->translator->trans('split_invoice.errors.no_participants');
        }

        // equal shares across everyone listed, the payer included; the payer's own
        // share isn't transferred — it already belongs to them
        $debtors = [];
        $debtorAmounts = [];
        if ($recipient) {
            $shares = $this->distributeAmount($amountCents ?? 0, max(1, count($cleanParticipants)));
            $i = 0;
            foreach ($cleanParticipants as $participant) {
                if ($participant->getId() !== $recipient->getId()) {
                    $debtors[] = $participant;
                    $debtorAmounts[] = $shares[$i];
                }
                ++$i;
            }
            if ([] === $debtors && count($cleanParticipants) > 0 && !$errors && !$rowErrors) {
                $errors[] = $this->translator->trans('split_invoice.errors.only_payer');
            }
        }

        if (!$errors && !$rowErrors && $recipient) {
            try {
                $this->transactionService->doSplit($debtors, $recipient, $debtorAmounts, $comment);
                $this->addFlash('success', $this->translator->trans('split_invoice.flash.success', [
                    '%count%' => count($cleanParticipants),
                    '%total%' => $this->appExtension->currencyFormat($amountCents, null, false),
                ]));
                $this->addFlash('transaction_success', '1');

                return $this->redirectToRoute('users_detail', ['id' => $recipient->getId()], Response::HTTP_SEE_OTHER);
            } catch (\Throwable $e) {
                $this->logger->error('Split invoice failed and was rolled back.', ['exception' => $e]);
                $errors[] = $this->translator->trans('split_invoice.flash.rolled_back');
            }
        }

        return $renderError();
    }

    /**
     * @param int[]         $participantIds
     * @param array<string> $rowErrors      keyed by row index
     *
     * @return array<int, User> keyed by original row index
     */
    private function resolveParticipants(array $participantIds, array &$rowErrors): array
    {
        $clean = [];
        foreach ($participantIds as $idx => $pid) {
            if ($pid <= 0) {
                continue;
            }
            $p = $this->userRepository->find($pid);
            if (!$p || $p->isDisabled()) {
                $rowErrors[$idx] = $this->translator->trans('transactions.errors.recipient_disabled');
                continue;
            }
            $clean[$idx] = $p;
        }

        return $clean;
    }

    /**
     * The remainder goes to the first rows so the total stays exact: 1001/3 → [334, 334, 333].
     *
     * @return int[]
     */
    private function distributeAmount(int $totalCents, int $count): array
    {
        $base = intdiv($totalCents, $count);
        $remainder = $totalCents - $base * $count;
        $amounts = array_fill(0, $count, $base);
        for ($i = 0; $i < $remainder; ++$i) {
            ++$amounts[$i];
        }

        return $amounts;
    }
}
