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
            'amount' => 500,
        ], 'transaction');

        $this->assertSame(500, $data['amount']);
        $this->assertSame($this->userId, $data['user']['id']);
        $this->assertSame(500, $data['user']['balance']);

        $this->assertUserBalance($this->userId, 500);
    }

    public function testRemoveBalanceAndUndo(): void
    {
        $payoutData = $this->requestJson('POST', "/api/user/{$this->userId}/transaction", [
            'amount' => -500,
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

    public function testGlobalListKeepsLegacyOldestFirstOrder(): void
    {
        $first = $this->requestJson('POST', "/api/user/{$this->userId}/transaction", ['amount' => 100], 'transaction');
        $second = $this->requestJson('POST', "/api/user/{$this->userId}/transaction", ['amount' => 200], 'transaction');

        $data = $this->requestJson('GET', '/api/transaction', unpackKey: 'transactions');

        // Legacy /api/transaction had no ORDER BY, so clients saw PK-ascending = oldest first.
        $ids = array_column($data, 'id');
        $this->assertSame([$first['id'], $second['id']], array_slice($ids, -2));
    }

    public function testUserTransactionListHonorsLimitAndOffset(): void
    {
        $user = $this->requestJson('POST', '/api/user', ['name' => 'pager'], 'user');
        foreach ([100, 200, 300] as $amount) {
            $this->requestJson('POST', sprintf('/api/user/%d/transaction', $user['id']), ['amount' => $amount]);
        }

        $all = $this->requestJson('GET', sprintf('/api/user/%d/transaction', $user['id']));
        $this->assertSame(3, $all['count']);
        $this->assertCount(3, $all['transactions']);

        $page = $this->requestJson('GET', sprintf('/api/user/%d/transaction?limit=1&offset=1', $user['id']));
        $this->assertSame(3, $page['count']);
        $this->assertCount(1, $page['transactions']);
        // newest-first ordering: offset 1 of [300, 200, 100] is the 200 deposit
        $this->assertSame(200, $page['transactions'][0]['amount']);
    }
}
