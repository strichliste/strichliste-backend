<?php

namespace App\Tests\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ArticleControllerTest extends AbstractApplicationTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = self::createUserDb('Alice');
    }

    public function testBuyArticleUpdatePriceAndUndo(): void
    {
        $articleData = $this->requestJson('POST', '/api/article', [
            'name' => 'Club Mate',
            'amount' => 150,
        ], 'article');

        $articleId = $articleData['id'];
        $this->assertSame(150, $articleData['amount']);
        $this->assertSame(0, $articleData['usageCount']);
        $this->assertTrue($articleData['isActive']);

        $buyData = $this->requestJson('POST', "/api/user/{$this->userId}/transaction", [
            'articleId' => $articleId,
        ], 'transaction');

        $transactionId = $buyData['id'];
        $this->assertSame(-150, $buyData['amount']);
        $this->assertSame(-150, $buyData['user']['balance']);

        $this->assertUserBalance($this->userId, -150);

        $afterBuy = $this->requestJson('GET', "/api/article/{$articleId}", unpackKey: 'article');
        
        $this->assertSame(1, $afterBuy['usageCount']);

        $updated = $this->requestJson('POST', "/api/article/{$articleId}", [
            'name' => 'Club Mate',
            'amount' => 200,
        ], 'article');

        $this->assertNotSame($articleId, $updated['id']);
        $this->assertSame(200, $updated['amount']);
        $this->assertTrue($updated['isActive']);
        $this->assertSame($articleId, $updated['precursor']['id']);
        $this->assertSame(1, $updated['usageCount']);

        $oldArticle = $this->requestJson('GET', "/api/article/{$articleId}", unpackKey: 'article');

        $this->assertSame($articleId, $oldArticle['id']);
        $this->assertFalse($oldArticle['isActive']);

        $undoData = $this->requestJson(
            'DELETE',
            "/api/user/{$this->userId}/transaction/{$transactionId}",
            unpackKey: 'transaction',
        );
        $this->assertTrue($undoData['isDeleted']);
        $this->assertSame(0, $undoData['user']['balance']);

        $this->assertUserBalance($this->userId, 0);

        $afterUndo = $this->requestJson('GET', "/api/article/{$articleId}", unpackKey: 'article');

        $this->assertSame(0, $afterUndo['usageCount']);
    }
}
