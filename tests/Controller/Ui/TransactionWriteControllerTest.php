<?php

namespace App\Tests\Controller\Ui;

use App\Entity\Transaction;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\DomCrawler\Crawler;

class TransactionWriteControllerTest extends AbstractUiTestCase
{
    private const string ERROR_INVALID = 'The transaction is invalid.';

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = $this->createUserDb('Alice');
    }

    /**
     * Step forms share one rendered CreateTransactionType view; pick by direction.
     */
    private function stepForm(Crawler $crawler, string $direction): Crawler
    {
        $forms = $crawler->filter('form.step-form')->reduce(
            fn (Crawler $node) => $direction === $node->filter('input[name="create_transaction[direction]"]')->attr('value'),
        );
        $this->assertGreaterThan(0, $forms->count(), sprintf('no "%s" step form in the response', $direction));

        return $forms->first();
    }

    /**
     * The custom form has two submit buttons sharing the direction name, so a
     * raw POST with the captured token is less brittle than Crawler::form().
     *
     * @param array<string, string> $fields
     */
    private function postCustomForm(int $userId, array $fields): void
    {
        $crawler = $this->client->request('GET', "/user/{$userId}");
        $token = $this->hiddenValue($crawler, 'create_transaction[_token]');

        $this->client->request('POST', "/user/{$userId}/transactions/create", [
            'create_transaction' => $fields + ['_token' => $token],
        ]);
    }

    /**
     * Books the smallest deposit step (0.50) and returns its transaction id —
     * the shared Arrange step of the undo tests.
     */
    private function depositViaStepForm(): int
    {
        $crawler = $this->client->request('GET', "/user/{$this->userId}");
        $this->client->submit($this->stepForm($crawler, 'deposit')->form());

        return $this->lastTransactionId($this->userId);
    }

    public function testDepositViaStepForm(): void
    {
        $crawler = $this->client->request('GET', "/user/{$this->userId}");

        $this->client->submit($this->stepForm($crawler, 'deposit')->form());

        $this->assertResponseRedirects("/user/{$this->userId}", 303);
        $this->client->followRedirect();
        $this->assertFlash('success', 'Deposit confirmed.');
        $this->assertUserBalance($this->userId, 50);
    }

    public function testDispenseViaStepForm(): void
    {
        $crawler = $this->client->request('GET', "/user/{$this->userId}");

        $this->client->submit($this->stepForm($crawler, 'dispense')->form());

        $this->assertResponseRedirects("/user/{$this->userId}", 303);
        $this->client->followRedirect();
        $this->assertFlash('success', 'Withdrawal confirmed.');
        $this->assertUserBalance($this->userId, -50);
    }

    public function testDepositViaCustomFormPersistsTheComment(): void
    {
        // the comment field is bound by the form type even though the custom
        // form doesn't render it — clients like the API docs rely on it
        $this->postCustomForm($this->userId, ['direction' => 'deposit', 'amount' => '5.00', 'comment' => 'lunch']);

        $this->assertResponseRedirects("/user/{$this->userId}", 303);
        $this->assertUserBalance($this->userId, 500);

        $transactions = $this->requestJson('GET', "/api/user/{$this->userId}/transaction", unpackKey: 'transactions');
        $this->assertSame('lunch', $transactions[0]['comment']);
    }

    public function testDispenseViaCustomForm(): void
    {
        $this->postCustomForm($this->userId, ['direction' => 'dispense', 'amount' => '5.00']);

        $this->assertResponseRedirects("/user/{$this->userId}", 303);
        $this->assertUserBalance($this->userId, -500);
    }

    public function testCraftedNegativeAmountCannotFlipADeposit(): void
    {
        $this->postCustomForm($this->userId, ['direction' => 'deposit', 'amount' => '-5.00']);

        $this->client->followRedirect();
        $this->assertFlash('success', 'Deposit confirmed.');
        $this->assertUserBalance($this->userId, 500);
    }

    #[TestWith(['abc'])]
    #[TestWith(['0'])]
    #[TestWith(['0.001'])] // rounds to zero cents — caught by the form or the controller's zero guard
    public function testNonPositiveAmountIsRejected(string $amount): void
    {
        $this->postCustomForm($this->userId, ['direction' => 'deposit', 'amount' => $amount]);

        $this->client->followRedirect();
        $this->assertFlash('error', self::ERROR_INVALID);
        $this->assertUserBalance($this->userId, 0);
    }

    public function testBadCsrfTokenIsRejected(): void
    {
        $this->client->request('POST', "/user/{$this->userId}/transactions/create", [
            'create_transaction' => ['direction' => 'deposit', 'amount' => '5.00', '_token' => 'garbage'],
        ]);

        $this->assertResponseRedirects("/user/{$this->userId}", 303);
        $this->client->followRedirect();
        $this->assertFlash('error', self::ERROR_INVALID);
        $this->assertUserBalance($this->userId, 0);
    }

    public function testDisabledUserCannotTransact(): void
    {
        $this->setUserDisabled($this->userId);

        // the disabled check runs before the form, no token needed
        $this->client->request('POST', "/user/{$this->userId}/transactions/create", [
            'create_transaction' => ['direction' => 'deposit', 'amount' => '5.00'],
        ]);

        $this->client->followRedirect();
        $this->assertFlash('error', self::ERROR_ACCOUNT_DISABLED);
        $this->assertUserBalance($this->userId, 0);
    }

    public function testPaymentBoundaryBlocksOversizedDeposit(): void
    {
        // 20000 cents > payment.boundary.upper (15000)
        $this->postCustomForm($this->userId, ['direction' => 'deposit', 'amount' => '200.00']);

        $this->client->followRedirect();
        $this->assertFlash('error', self::ERROR_BOUNDARY);
        $this->assertUserBalance($this->userId, 0);
    }

    public function testAccountBoundaryBlocksSecondDeposit(): void
    {
        $this->postCustomForm($this->userId, ['direction' => 'deposit', 'amount' => '140.00']);
        $this->assertUserBalance($this->userId, 14000);

        // 14000 + 14000 would cross account.boundary.upper (20000)
        $this->postCustomForm($this->userId, ['direction' => 'deposit', 'amount' => '140.00']);

        $this->client->followRedirect();
        $this->assertFlash('error', self::ERROR_BOUNDARY);
        $this->assertUserBalance($this->userId, 14000);
    }

    public function testTransferMovesMoneyAndPersistsTheCommentOnBothRows(): void
    {
        $bobId = $this->createUserDb('Bob');

        $crawler = $this->client->request('GET', "/user/{$this->userId}?tab=send");
        $form = $crawler->filter('form.send-form-wrap')->form([
            'transfer_transaction[amount]' => '5.00',
            'transfer_transaction[recipient]' => (string) $bobId,
            'transfer_transaction[comment]' => 'pizza',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects("/user/{$this->userId}", 303);
        $this->client->followRedirect();
        $this->assertFlash('success', 'Transferred to Bob.');
        $this->assertUserBalance($this->userId, -500);
        $this->assertUserBalance($bobId, 500);

        $senderTx = $this->requestJson('GET', "/api/user/{$this->userId}/transaction", unpackKey: 'transactions');
        $recipientTx = $this->requestJson('GET', "/api/user/{$bobId}/transaction", unpackKey: 'transactions');
        $this->assertSame('pizza', $senderTx[0]['comment']);
        $this->assertSame('pizza', $recipientTx[0]['comment']);
    }

    public function testTransferWithoutRecipientReopensSendTab(): void
    {
        $this->createUserDb('Bob');

        $crawler = $this->client->request('GET', "/user/{$this->userId}?tab=send");
        $form = $crawler->filter('form.send-form-wrap')->form([
            'transfer_transaction[amount]' => '5.00',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects("/user/{$this->userId}?tab=send", 303);
        $this->client->followRedirect();
        $this->assertFlash('error', self::ERROR_INVALID);
        $this->assertUserBalance($this->userId, 0);
    }

    public function testTransferToForgedSelfIdIsRejected(): void
    {
        // the EntityType choice list excludes the sender, so a forged own-id
        // POST fails form validation before the explicit self-transfer check
        $this->createUserDb('Bob');

        $crawler = $this->client->request('GET', "/user/{$this->userId}?tab=send");
        $token = $this->hiddenValue($crawler, 'transfer_transaction[_token]');

        $this->client->request('POST', "/user/{$this->userId}/transactions/transfer", [
            'transfer_transaction' => [
                'amount' => '5.00',
                'recipient' => (string) $this->userId,
                '_token' => $token,
            ],
        ]);

        $this->client->followRedirect();
        $this->assertFlash('error', self::ERROR_INVALID);
        $this->assertUserBalance($this->userId, 0);
    }

    public function testTransferToDisabledRecipientIsRejected(): void
    {
        $bobId = $this->createUserDb('Bob');

        // capture the form while Bob is still a valid choice, then disable him
        $crawler = $this->client->request('GET', "/user/{$this->userId}?tab=send");
        $token = $this->hiddenValue($crawler, 'transfer_transaction[_token]');
        $this->setUserDisabled($bobId);

        $this->client->request('POST', "/user/{$this->userId}/transactions/transfer", [
            'transfer_transaction' => [
                'amount' => '5.00',
                'recipient' => (string) $bobId,
                '_token' => $token,
            ],
        ]);

        $this->client->followRedirect();
        $this->assertFlash('error', self::ERROR_INVALID);
        $this->assertUserBalance($this->userId, 0);
        $this->assertUserBalance($bobId, 0);
    }

    public function testUndoWithinGraceSoftDeletes(): void
    {
        $txId = $this->depositViaStepForm();
        $this->assertUserBalance($this->userId, 50);

        $crawler = $this->client->request('GET', "/user/{$this->userId}");
        $this->client->submit($crawler->filter('form.undo-form')->form());

        $this->assertResponseRedirects("/user/{$this->userId}", 303);
        $this->client->followRedirect();
        $this->assertFlash('success', 'Transaction reverted.');
        $this->assertUserBalance($this->userId, 0);

        // payment.undo.delete is false by default: the row survives as reverted
        $em = $this->em();
        $em->clear();
        $tx = $em->find(Transaction::class, $txId);
        $this->assertNotNull($tx);
        $this->assertTrue($tx->isDeleted());
    }

    public function testUndoWithDeleteSettingRemovesTheRow(): void
    {
        $this->overrideSettings(['payment' => ['undo' => ['delete' => true]]]);

        $txId = $this->depositViaStepForm();

        $crawler = $this->client->request('GET', "/user/{$this->userId}");
        $this->client->submit($crawler->filter('form.undo-form')->form());

        $this->client->followRedirect();
        $this->assertFlash('success', 'Transaction reverted.');
        $this->assertUserBalance($this->userId, 0);

        $em = $this->em();
        $em->clear();
        $this->assertNull($em->find(Transaction::class, $txId));
    }

    public function testUndoOfATransferFromTheRecipientsPageRestoresBothSides(): void
    {
        $bobId = $this->createUserDb('Bob');
        $crawler = $this->client->request('GET', "/user/{$this->userId}?tab=send");
        $this->client->submit($crawler->filter('form.send-form-wrap')->form([
            'transfer_transaction[amount]' => '5.00',
            'transfer_transaction[recipient]' => (string) $bobId,
        ]));
        $senderTxId = $this->lastTransactionId($this->userId);
        $recipientTxId = $this->lastTransactionId($bobId);

        // the recipient sees the paired row and may undo from their own page
        $crawler = $this->client->request('GET', "/user/{$bobId}");
        $this->client->submit($crawler->filter('form.undo-form')->form());

        $this->assertResponseRedirects("/user/{$bobId}", 303);
        $this->client->followRedirect();
        $this->assertFlash('success', 'Transaction reverted.');
        $this->assertUserBalance($this->userId, 0);
        $this->assertUserBalance($bobId, 0);

        $em = $this->em();
        $em->clear();
        $this->assertTrue($em->find(Transaction::class, $senderTxId)?->isDeleted());
        $this->assertTrue($em->find(Transaction::class, $recipientTxId)?->isDeleted());
    }

    public function testUndoOutsideGraceIsRejected(): void
    {
        $txId = $this->depositViaStepForm();

        // a stale tab keeps a valid CSRF token forever — capture the form,
        // expire the window, then submit the stale form
        $crawler = $this->client->request('GET', "/user/{$this->userId}");
        $undoForm = $crawler->filter('form.undo-form')->form();
        $this->backdateTransaction($txId);

        $this->client->submit($undoForm);

        $this->client->followRedirect();
        $this->assertFlash('error', 'This transaction can no longer be undone.');
        $this->assertUserBalance($this->userId, 50);

        $em = $this->em();
        $em->clear();
        $tx = $em->find(Transaction::class, $txId);
        $this->assertNotNull($tx);
        $this->assertFalse($tx->isDeleted());

        // and a fresh render no longer offers the undo button at all
        $this->client->request('GET', "/user/{$this->userId}");
        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(0, 'form.undo-form');
    }

    public function testUndoFromHistoryReturnsToHistory(): void
    {
        $this->depositViaStepForm();

        $crawler = $this->client->request('GET', "/user/{$this->userId}/transactions");
        $this->client->submit($crawler->filter('form.undo-form')->form());

        $this->assertResponseRedirects("/user/{$this->userId}/transactions", 303);
        $this->assertUserBalance($this->userId, 0);
    }

    public function testUndoForAnotherUsersTransactionIsRejected(): void
    {
        $bobId = $this->createUserDb('Bob');
        $txId = $this->depositViaStepForm();

        // harvest Alice's real undo token: it is scoped to her user+tx pair,
        // so replaying it against Bob's URL must die at the CSRF check — and
        // if that scoping ever loosens, the ownership 404 behind it flips
        // this from a 303 into a failing assertion
        $crawler = $this->client->request('GET', "/user/{$this->userId}");
        $aliceToken = $this->hiddenValue($crawler, '_token');

        $this->client->request('POST', "/user/{$bobId}/transactions/{$txId}/undo", [
            '_token' => $aliceToken,
        ]);

        $this->assertResponseRedirects("/user/{$bobId}", 303);
        $this->client->followRedirect();
        $this->assertFlash('error', self::ERROR_GENERIC);
        $this->assertUserBalance($this->userId, 50);

        $em = $this->em();
        $em->clear();
        $this->assertFalse($em->find(Transaction::class, $txId)?->isDeleted());
    }
}
