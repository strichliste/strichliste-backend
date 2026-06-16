<?php

namespace App\Tests\Controller\Api;

use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;

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
        ]);

        $this->assertSame(500, $data['amount']);
        $this->assertSame($this->userId, $data['user']['id']);
        $this->assertSame(500, $data['user']['balance']);

        $this->assertUserBalance($this->userId, 500);
    }

    public function testRemoveBalanceAndUndo(): void
    {
        $payoutData = $this->requestJson('POST', "/api/user/{$this->userId}/transaction", [
            'amount' => -500,
        ]);

        $this->assertSame(-500, $payoutData['amount']);
        $this->assertSame(-500, $payoutData['user']['balance']);

        $this->assertUserBalance($this->userId, -500);

        $undoData = $this->requestJson(
            'DELETE',
            "/api/user/{$this->userId}/transaction/{$payoutData['id']}",
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
        ]);

        $this->assertSame(-500, $sendData['amount']);
        $this->assertSame(-500, $sendData['user']['balance']);

        $this->assertUserBalance($this->userId, -500);
        $this->assertUserBalance($recipientId, 500);

        $undoData = $this->requestJson(
            'DELETE',
            "/api/user/{$this->userId}/transaction/{$sendData['id']}",
        );
        $this->assertTrue($undoData['isDeleted']);
        $this->assertSame(0, $undoData['user']['balance']);

        $this->assertUserBalance($this->userId, 0);
        $this->assertUserBalance($recipientId, 0);
    }

    public function testCannotDeleteAnotherUsersTransaction(): void
    {
        $malloryId = $this->createUserDb('Mallory');

        $payout = $this->requestJson('POST', "/api/user/{$this->userId}/transaction", [
            'amount' => -500,
        ]);

        // Mallory tries to revert Alice's transaction through her own {userId} scope.
        $this->client->request('DELETE', "/api/user/{$malloryId}/transaction/{$payout['id']}");
        $this->assertResponseStatusCodeSame(404);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(\App\Exception\TransactionNotFoundException::class, $body['error']['class']);

        // Alice's transaction (and balance) must be untouched.
        $this->assertUserBalance($this->userId, -500);
    }

    public function testDeleteWithUnknownUserReturns404(): void
    {
        $payout = $this->requestJson('POST', "/api/user/{$this->userId}/transaction", [
            'amount' => -500,
        ]);

        $this->client->request('DELETE', "/api/user/999999/transaction/{$payout['id']}");
        $this->assertResponseStatusCodeSame(404);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(\App\Exception\UserNotFoundException::class, $body['error']['class']);

        $this->assertUserBalance($this->userId, -500);
    }

    public function testDeleteUnknownTransactionReturns404(): void
    {
        $this->client->request('DELETE', "/api/user/{$this->userId}/transaction/999999");
        $this->assertResponseStatusCodeSame(404);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(\App\Exception\TransactionNotFoundException::class, $body['error']['class']);
    }

    public function testCannotDeleteTransactionPastUndoTimeout(): void
    {
        $payout = $this->requestJson('POST', "/api/user/{$this->userId}/transaction", [
            'amount' => -500,
        ]);

        // age the transaction beyond the configured undo timeout (payment.undo.timeout = 5 minute)
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $transaction = $em->getRepository(Transaction::class)->find($payout['id']);
        $transaction->setCreated(new \DateTime('-1 hour'));
        $em->flush();

        $this->client->request('DELETE', "/api/user/{$this->userId}/transaction/{$payout['id']}");
        $this->assertResponseStatusCodeSame(400);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(\App\Exception\TransactionNotDeletableException::class, $body['error']['class']);

        // the stale transaction was not reverted, so the balance is unchanged
        $this->assertUserBalance($this->userId, -500);
    }

    public function testCannotUndoAnAlreadyUndoneTransaction(): void
    {
        $payout = $this->requestJson('POST', "/api/user/{$this->userId}/transaction", [
            'amount' => -500,
        ]);

        // first undo succeeds and restores the balance
        $this->requestJson(
            'DELETE',
            "/api/user/{$this->userId}/transaction/{$payout['id']}",
        );
        $this->assertUserBalance($this->userId, 0);

        // a second undo of the same (now deleted) transaction is rejected and does not double-credit
        $this->client->request('DELETE', "/api/user/{$this->userId}/transaction/{$payout['id']}");
        $this->assertResponseStatusCodeSame(400);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(\App\Exception\TransactionNotDeletableException::class, $body['error']['class']);

        $this->assertUserBalance($this->userId, 0);
    }

    public function testGlobalListKeepsLegacyOldestFirstOrder(): void
    {
        $first = $this->requestJson('POST', "/api/user/{$this->userId}/transaction", ['amount' => 100]);
        $second = $this->requestJson('POST', "/api/user/{$this->userId}/transaction", ['amount' => 200]);

        $data = $this->requestJson('GET', '/api/transaction', unpackKey: 'transactions');

        // Legacy /api/transaction had no ORDER BY, so clients saw PK-ascending = oldest first.
        $ids = array_column($data, 'id');
        $this->assertSame([$first['id'], $second['id']], array_slice($ids, -2));
    }

    public function testUserTransactionListHonorsLimitAndOffset(): void
    {
        $user = $this->requestJson('POST', '/api/user', ['name' => 'pager']);
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
