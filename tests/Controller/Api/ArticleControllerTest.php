<?php

namespace App\Tests\Controller\Api;

class ArticleControllerTest extends AbstractApplicationTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = self::createUserDb('Alice');
    }

    public function testCreateArticle(): void
    {
        $article = $this->requestJson('POST', '/api/article', [
            'name' => '  Club Mate  ', // Spaces are intentional and expected to be trim()ed
            'amount' => 150,
        ], 'article');

        $this->assertIsInt($article['id']);
        $this->assertSame('Club Mate', $article['name']);
        $this->assertSame(150, $article['amount']);
        $this->assertTrue($article['isActive']);
        $this->assertSame(0, $article['usageCount']);
        $this->assertSame([], $article['barcodes']);
        $this->assertSame([], $article['tags']);
        $this->assertNull($article['precursor']);
        $this->assertNotEmpty($article['created']);

        $fetched = $this->requestJson('GET', "/api/article/{$article['id']}", unpackKey: 'article');
        $this->assertSame($article['id'], $fetched['id']);
        $this->assertSame('Club Mate', $fetched['name']);
        $this->assertSame(150, $fetched['amount']);
    }

    public function testBuyArticleUpdatePriceAndUndo(): void
    {
        $articleId = $this->createArticleDb('Club Mate', 150);

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

    public function testArticleUpdateMigratesBarcodesAndTags(): void
    {
        $oldId = $this->createArticleDb('Club Mate', 150);

        $barcode = $this->generateBarcode();
        $this->requestJson('POST', "/api/article/{$oldId}/barcode", ['barcode' => $barcode]);
        $this->requestJson('POST', "/api/article/{$oldId}/tag", ['tag' => 'mate']);
        $this->requestJson('POST', "/api/article/{$oldId}/tag", ['tag' => 'caffeine']);

        // Create transaction so article is not updated in place but a new article with precursor is created
        $this->requestJson('POST', "/api/user/{$this->userId}/transaction", ['articleId' => $oldId]);

        $updated = $this->requestJson('POST', "/api/article/{$oldId}", [
            'name' => 'Club Mate',
            'amount' => 200,
        ], 'article');

        $newId = $updated['id'];
        $this->assertNotSame($oldId, $newId);
        $this->assertSame($oldId, $updated['precursor']['id']);

        $newArticle = $this->requestJson('GET', "/api/article/{$newId}", unpackKey: 'article');

        $this->assertCount(1, $newArticle['barcodes']);
        $this->assertSame($barcode, $newArticle['barcodes'][0]['barcode']);

        $tags = array_column($newArticle['tags'], 'tag');
        $this->assertEqualsCanonicalizing(['mate', 'caffeine'], $tags);

        $oldArticle = $this->requestJson('GET', "/api/article/{$oldId}", unpackKey: 'article');
        $this->assertFalse($oldArticle['isActive']);
        $this->assertSame([], $oldArticle['barcodes']);
        $this->assertSame([], $oldArticle['tags']);
    }

    public function testDeleteArticleRemovesBarcodesButKeepsTags(): void
    {
        $articleId = $this->createArticleDb('Club Mate', 150);
        $barcode = $this->generateBarcode();

        $this->requestJson('POST', "/api/article/{$articleId}/barcode", ['barcode' => $barcode]);
        $this->requestJson('POST', "/api/article/{$articleId}/tag", ['tag' => 'mate']);

        $deleted = $this->requestJson('DELETE', "/api/article/{$articleId}", unpackKey: 'article');

        $this->assertFalse($deleted['isActive']);
        $this->assertSame([], $deleted['barcodes']);
        $this->assertCount(1, $deleted['tags']);
        $this->assertSame('mate', $deleted['tags'][0]['tag']);

        $list = $this->requestJson('GET', "/api/article/{$articleId}/barcode");
        $this->assertSame(0, $list['count']);
    }

    public function testSearchByBarcode(): void
    {
        $articleId = $this->createArticleDb('Club Mate', 150);
        $otherId = $this->createArticleDb('Flora Mate', 160);

        $barcode = $this->generateBarcode();
        $otherBarcode = $this->generateBarcode();

        $this->requestJson('POST', "/api/article/{$articleId}/barcode", ['barcode' => $barcode]);
        $this->requestJson('POST', "/api/article/{$otherId}/barcode", ['barcode' => $otherBarcode]);

        // This endpoint is used by the frontend if an article is scanned,
        // the result is then sent to POST /user/<id>/transaction

        $hit = $this->requestJson('GET', '/api/article/search', ['barcode' => $barcode]);
        $this->assertSame(1, $hit['count']);
        $this->assertSame($articleId, $hit['articles'][0]['id']);
        $this->assertSame($barcode, $hit['articles'][0]['barcodes'][0]['barcode']);
    }
}
