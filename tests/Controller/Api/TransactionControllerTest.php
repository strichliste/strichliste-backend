<?php

namespace App\Tests\Controller\Api;

class TransactionControllerTest extends AbstractApplicationTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = $this->createUserDb('Alice');
    }

    public function testAddBalance(): void
    {
        $data = $this->requestJson('POST', "/api/user/{$this->userId}/transaction", [
            'amount' => 500
        ], 'transaction');

        $this->assertSame(500, $data['amount']);
        $this->assertSame($this->userId, $data['user']['id']);
        $this->assertSame(500, $data['user']['balance']);

        $this->assertUserBalance($this->userId, 500);
    }

    public function testRemoveBalanceAndUndo(): void
    {
        $payoutData = $this->requestJson('POST', "/api/user/{$this->userId}/transaction", [
            'amount' => -500
        ], 'transaction');

        $this->assertSame(-500, $payoutData['amount']);
        $this->assertSame(-500, $payoutData['user']['balance']);

        $this->assertUserBalance($this->userId, -500);

        $undoData = $this->requestJson(
            'DELETE',
            "/api/user/{$this->userId}/transaction/{$payoutData['id']}",
            unpackKey: 'transaction',
        );
        $this->assertTrue($undoData['isDeleted']);
        $this->assertSame(0, $undoData['user']['balance']);

        $this->assertUserBalance($this->userId, 0);
    }

    public function testSendMoneyAndUndo(): void
    {
        $recipientId = $this->createUserDb('Bob');

        $sendData = $this->requestJson('POST', "/api/user/{$this->userId}/transaction", [
            'amount' => -500,
            'recipientId' => $recipientId,
        ], 'transaction');

        $this->assertSame(-500, $sendData['amount']);
        $this->assertSame(-500, $sendData['user']['balance']);

        $this->assertUserBalance($this->userId, -500);
        $this->assertUserBalance($recipientId, 500);

        $undoData = $this->requestJson(
            'DELETE',
            "/api/user/{$this->userId}/transaction/{$sendData['id']}",
            unpackKey: 'transaction',
        );
        $this->assertTrue($undoData['isDeleted']);
        $this->assertSame(0, $undoData['user']['balance']);

        $this->assertUserBalance($this->userId, 0);
        $this->assertUserBalance($recipientId, 0);
    }
}
