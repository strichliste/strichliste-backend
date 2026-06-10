<?php

namespace App\Tests\Controller\Api;

/**
 * Pins the frozen /api/metrics and /api/user/{id}/metrics JSON contract for
 * exactly the surfaces the symfony-ux branch rewrote (MetricsService).
 */
class MetricsControllerTest extends AbstractApplicationTestCase
{
    public function testGlobalMetricsShape(): void
    {
        $userId = $this->createUserDb('Alice');
        $this->requestJson('POST', "/api/user/{$userId}/transaction", ['amount' => 500]);
        $this->requestJson('POST', "/api/user/{$userId}/transaction", ['amount' => -200]);

        $data = $this->requestJson('GET', '/api/metrics?days=3');

        $this->assertSame(300, $data['balance']);
        $this->assertSame(2, $data['transactionCount']);
        $this->assertSame(1, $data['userCount']);
        $this->assertCount(4, $data['days']); // today + 3 prior days

        // Legacy shape: today (has transactions) nests {amount, transactions};
        // quiet days carry SCALAR zeroes — clients parse them as numbers.
        $byDate = array_column($data['days'], null, 'date');
        $today = $byDate[date('Y-m-d')];
        $this->assertSame(['amount' => 500, 'transactions' => 1], $today['charged']);
        $this->assertSame(['amount' => 200, 'transactions' => 1], $today['spent']);
        $this->assertSame(300, $today['balance']);
        $this->assertSame(1, $today['distinctUsers']);

        unset($byDate[date('Y-m-d')]);
        $quietDay = array_shift($byDate);
        $this->assertSame(0, $quietDay['charged']);
        $this->assertSame(0, $quietDay['spent']);
        $this->assertSame(0, $quietDay['transactions']);
    }

    public function testDaysParameterIsClamped(): void
    {
        // Negative used to throw a 500 from DateTime; huge values allocated
        // one array row per day before any query ran.
        $this->client->request('GET', '/api/metrics?days=-5');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/api/metrics?days=100000000');
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertLessThanOrEqual(3651, count($data['days']));
    }

    public function testUserMetricsIncludeRevertedPurchases(): void
    {
        $userId = $this->createUserDb('Bob');
        $articleId = $this->createArticleDb('Club Mate', 150);
        $this->requestJson('POST', "/api/user/{$userId}/transaction", ['amount' => 500]);
        $tx = $this->requestJson('POST', "/api/user/{$userId}/transaction", [
            'articleId' => $articleId,
        ], 'transaction');
        $this->requestJson('DELETE', "/api/user/{$userId}/transaction/{$tx['id']}");

        $data = $this->requestJson('GET', "/api/user/{$userId}/metrics");

        // Legacy behavior: the per-article breakdown counted reverted
        // purchases (only transactions.count excluded them — asymmetry is
        // part of the frozen contract).
        $this->assertCount(1, $data['articles']);
        $this->assertSame(1, $data['articles'][0]['count']);
        $this->assertSame('Club Mate', $data['articles'][0]['article']['name']);
    }
}
