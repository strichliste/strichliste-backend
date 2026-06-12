<?php

namespace App\Tests\Controller\Ui;

class BuyArticleControllerTest extends AbstractUiTestCase
{
    private int $userId;
    private int $articleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = $this->createUserDb('Alice');
        $this->articleId = $this->createArticleDb('Club Mate', 150);
    }

    /**
     * @return array<mixed>
     */
    private function articleJson(): array
    {
        return $this->requestJson('GET', "/api/article/{$this->articleId}", unpackKey: 'article');
    }

    public function testBuyByArticlePill(): void
    {
        $crawler = $this->client->request('GET', "/user/{$this->userId}?tab=buy");

        $this->client->submit($crawler->filter('form.buy-tab__pill-form')->form());

        $this->assertResponseRedirects("/user/{$this->userId}", 303);
        $this->client->followRedirect();
        $this->assertFlash('success', 'Bought Club Mate.');
        $this->assertUserBalance($this->userId, -150);
        $this->assertSame(1, $this->articleJson()['usageCount']);
    }

    public function testBuyByBarcode(): void
    {
        $code = $this->generateBarcode();
        $this->createBarcodeDb($this->articleId, $code);

        $crawler = $this->client->request('GET', "/user/{$this->userId}?tab=buy");
        $form = $crawler->filter('form.buy-tab__scan')->form(['barcode' => $code]);
        $this->client->submit($form);

        $this->client->followRedirect();
        $this->assertFlash('success', 'Bought Club Mate.');
        $this->assertUserBalance($this->userId, -150);
    }

    public function testUnknownBarcodeIsReportedWithTheCode(): void
    {
        $crawler = $this->client->request('GET', "/user/{$this->userId}?tab=buy");
        $form = $crawler->filter('form.buy-tab__scan')->form(['barcode' => '0000000000000']);
        $this->client->submit($form);

        $this->client->followRedirect();
        $this->assertFlash('error', 'No article with barcode "0000000000000".');
        $this->assertUserBalance($this->userId, 0);
    }

    public function testBadCsrfTokenIsRejected(): void
    {
        $this->client->request('POST', "/user/{$this->userId}/transactions/buy", [
            '_token' => 'garbage',
            'articleId' => (string) $this->articleId,
        ]);

        $this->assertResponseRedirects("/user/{$this->userId}", 303);
        $this->client->followRedirect();
        $this->assertFlash('error', self::ERROR_GENERIC);
        $this->assertUserBalance($this->userId, 0);
    }

    public function testDisabledUserCannotBuy(): void
    {
        // a stale tab still holds a valid token; the disabled check runs after CSRF
        $crawler = $this->client->request('GET', "/user/{$this->userId}?tab=buy");
        $form = $crawler->filter('form.buy-tab__pill-form')->form();
        $this->setUserDisabled($this->userId);

        $this->client->submit($form);

        $this->client->followRedirect();
        $this->assertFlash('error', self::ERROR_ACCOUNT_DISABLED);
        $this->assertUserBalance($this->userId, 0);
    }

    public function testStalePillForDeactivatedArticleFallsToUnknown(): void
    {
        // pill lookup goes through findOneActive(), so a deactivated article
        // resolves to "no article" rather than ArticleInactiveException
        $crawler = $this->client->request('GET', "/user/{$this->userId}?tab=buy");
        $form = $crawler->filter('form.buy-tab__pill-form')->form();
        $this->setArticleInactive($this->articleId);

        $this->client->submit($form);

        $this->client->followRedirect();
        $this->assertFlash('error', 'No article with barcode');
        $this->assertUserBalance($this->userId, 0);
    }

    public function testBarcodeOfDeactivatedArticleReportsInactive(): void
    {
        // the barcode lookup does not filter on active, so this is the one
        // path that reaches TransactionService's ArticleInactiveException
        $code = $this->generateBarcode();
        $this->createBarcodeDb($this->articleId, $code);
        $this->setArticleInactive($this->articleId);

        $crawler = $this->client->request('GET', "/user/{$this->userId}?tab=buy");
        $form = $crawler->filter('form.buy-tab__scan')->form(['barcode' => $code]);
        $this->client->submit($form);

        $this->client->followRedirect();
        $this->assertFlash('error', 'This article is inactive.');
        $this->assertUserBalance($this->userId, 0);
        $this->assertSame(0, $this->articleJson()['usageCount']);
    }

    public function testAccountBoundaryBlocksPurchase(): void
    {
        // -19900 - 150 would cross account.boundary.lower (-20000)
        $this->setUserBalance($this->userId, -19900);

        $crawler = $this->client->request('GET', "/user/{$this->userId}?tab=buy");
        $this->client->submit($crawler->filter('form.buy-tab__pill-form')->form());

        $this->client->followRedirect();
        $this->assertFlash('error', self::ERROR_BOUNDARY);
        $this->assertUserBalance($this->userId, -19900);
        $this->assertSame(0, $this->articleJson()['usageCount']);
    }
}
