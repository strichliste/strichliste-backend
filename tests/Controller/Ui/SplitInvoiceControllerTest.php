<?php

namespace App\Tests\Controller\Ui;

class SplitInvoiceControllerTest extends AbstractUiTestCase
{
    private int $payerId;
    private int $bobId;
    private int $carolId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payerId = $this->createUserDb('Alice');
        $this->bobId = $this->createUserDb('Bob');
        $this->carolId = $this->createUserDb('Carol');
    }

    /**
     * The participant rows are dynamic, so the POST is sent raw with the
     * captured token instead of through Crawler::form().
     *
     * @param array<string, mixed> $fields
     */
    private function postSplit(array $fields): void
    {
        $crawler = $this->client->request('GET', '/split-invoice');
        $token = $this->hiddenValue($crawler, '_token');

        $this->client->request('POST', '/split-invoice', $fields + ['_token' => $token]);
    }

    private function assertNoTransactionsBooked(): void
    {
        $this->assertCount(0, $this->requestJson('GET', '/api/transaction', unpackKey: 'transactions'));
    }

    public function testFormRendersWithOneParticipantRow(): void
    {
        $this->client->request('GET', '/split-invoice');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(1, 'ul.split-invoice-participants-list select[name="participants[]"]');
    }

    public function testDisabledFeatureIs404(): void
    {
        $this->overrideSettings(['payment' => ['splitInvoice' => ['enabled' => false]]]);

        $this->client->request('GET', '/split-invoice');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testEqualSplitWithPayerOnTheList(): void
    {
        $this->postSplit([
            'amount' => '30.00',
            'recipient' => (string) $this->payerId,
            'participants' => [(string) $this->payerId, (string) $this->bobId, (string) $this->carolId],
        ]);

        $this->assertResponseRedirects("/user/{$this->payerId}", 303);
        $this->client->followRedirect();
        $this->assertFlash('success', 'with 3 participants');

        // the payer's own 10.00 share stays put; only Bob's and Carol's move
        $this->assertUserBalance($this->payerId, 2000);
        $this->assertUserBalance($this->bobId, -1000);
        $this->assertUserBalance($this->carolId, -1000);
    }

    public function testSplitWithRecipientNotParticipating(): void
    {
        // the divisor is the participant count — the recipient being absent
        // from the list must not change anyone's share
        $this->postSplit([
            'amount' => '30.00',
            'recipient' => (string) $this->payerId,
            'participants' => [(string) $this->bobId, (string) $this->carolId],
        ]);

        $this->client->followRedirect();
        $this->assertFlash('success', 'with 2 participants');
        $this->assertUserBalance($this->payerId, 3000);
        $this->assertUserBalance($this->bobId, -1500);
        $this->assertUserBalance($this->carolId, -1500);
    }

    public function testDuplicateParticipantRowsChargeOneSharePerRow(): void
    {
        // pins current behavior: every row is a share, even when the same
        // user fills two rows ("Bob covers two people") — a dedupe refactor
        // would silently change booked amounts
        $this->postSplit([
            'amount' => '10.00',
            'recipient' => (string) $this->payerId,
            'participants' => [(string) $this->bobId, (string) $this->bobId],
        ]);

        $this->client->followRedirect();
        $this->assertUserBalance($this->payerId, 1000);
        $this->assertUserBalance($this->bobId, -1000);
    }

    public function testAmountsGoThroughTheStrictMoneyParser(): void
    {
        // "1.000" is ambiguous (1000 vs 1.000) and refused outright rather
        // than risking a booking off by x1000
        $this->postSplit([
            'amount' => '1.000',
            'recipient' => (string) $this->payerId,
            'participants' => [(string) $this->bobId],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertFlash('error', 'Total amount must be positive.');
        $this->assertNoTransactionsBooked();

        // comma decimals are kiosk reality and parse as cents
        $this->postSplit([
            'amount' => '7,50',
            'recipient' => (string) $this->payerId,
            'participants' => [(string) $this->payerId, (string) $this->bobId],
        ]);

        $this->client->followRedirect();
        $this->assertUserBalance($this->payerId, 375);
        $this->assertUserBalance($this->bobId, -375);
    }

    public function testRemainderGoesToTheFirstRows(): void
    {
        // 1001 / 3 → [334, 334, 333]; the payer holds the first share
        $this->postSplit([
            'amount' => '10.01',
            'recipient' => (string) $this->payerId,
            'participants' => [(string) $this->payerId, (string) $this->bobId, (string) $this->carolId],
        ]);

        $this->client->followRedirect();
        $this->assertUserBalance($this->payerId, 667);
        $this->assertUserBalance($this->bobId, -334);
        $this->assertUserBalance($this->carolId, -333);
    }

    public function testInvalidAmountRendersA422(): void
    {
        $this->postSplit([
            'amount' => 'abc',
            'recipient' => (string) $this->payerId,
            'participants' => [(string) $this->bobId],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertFlash('error', 'Total amount must be positive.');
        $this->assertNoTransactionsBooked();
    }

    public function testMissingRecipientRendersA422(): void
    {
        $this->postSplit([
            'amount' => '10.00',
            'recipient' => '',
            'participants' => [(string) $this->bobId],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertFlash('error', 'Pick a valid recipient.');
        $this->assertNoTransactionsBooked();
    }

    public function testNoParticipantsRendersA422(): void
    {
        $this->postSplit([
            'amount' => '10.00',
            'recipient' => (string) $this->payerId,
            'participants' => [''],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertFlash('error', 'Pick at least one participant.');
        $this->assertNoTransactionsBooked();
    }

    public function testOnlyPayerRendersA422(): void
    {
        $this->postSplit([
            'amount' => '10.00',
            'recipient' => (string) $this->payerId,
            'participants' => [(string) $this->payerId],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertFlash('error', 'Add at least one person besides the payer.');
        $this->assertNoTransactionsBooked();
    }

    public function testBadCsrfTokenRendersA422(): void
    {
        $this->client->request('POST', '/split-invoice', [
            '_token' => 'garbage',
            'amount' => '10.00',
            'recipient' => (string) $this->payerId,
            'participants' => [(string) $this->bobId],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertFlash('error', self::ERROR_GENERIC);
        $this->assertNoTransactionsBooked();
    }

    public function testNoJsAddRowAppendsARowAndKeepsSelections(): void
    {
        $this->postSplit([
            'add_row' => '1',
            'amount' => '10.00',
            'recipient' => (string) $this->payerId,
            'participants' => [(string) $this->payerId, (string) $this->bobId],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorCount(3, 'ul.split-invoice-participants-list select[name="participants[]"]');
        $crawler = $this->client->getCrawler();

        $selected = $crawler->filter('ul.split-invoice-participants-list option[selected]')
            ->each(fn ($node) => $node->attr('value'));
        $this->assertSame([(string) $this->payerId, (string) $this->bobId], $selected);

        $this->assertNoTransactionsBooked();
    }

    public function testDisabledParticipantFromStaleFormGetsARowError(): void
    {
        $crawler = $this->client->request('GET', '/split-invoice');
        $token = $this->hiddenValue($crawler, '_token');
        $this->setUserDisabled($this->carolId);

        $this->client->request('POST', '/split-invoice', [
            '_token' => $token,
            'amount' => '10.00',
            'recipient' => (string) $this->payerId,
            'participants' => [(string) $this->bobId, (string) $this->carolId],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.row-error', 'Recipient is not available.');
        $this->assertSelectorCount(1, 'select[name="participants[]"][aria-invalid="true"]');
        $this->assertNoTransactionsBooked();
    }

    public function testBoundaryViolationRollsBackTheWholeSplit(): void
    {
        // Carol's 15.00 share would cross account.boundary.lower (-20000);
        // Bob's already-booked transfer must roll back with it
        $this->setUserBalance($this->carolId, -19500);

        $this->postSplit([
            'amount' => '30.00',
            'recipient' => (string) $this->payerId,
            'participants' => [(string) $this->bobId, (string) $this->carolId],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertFlash('error', 'No transfers created.');

        $this->assertUserBalance($this->payerId, 0);
        $this->assertUserBalance($this->bobId, 0);
        $this->assertUserBalance($this->carolId, -19500);
        $this->assertNoTransactionsBooked();
    }
}
