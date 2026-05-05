<?php

namespace App\Tests\Controller\Api;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\Response;

class BarcodeControllerTest extends AbstractApplicationTestCase
{
    #[TestWith([null]), TestWith(['']), TestWith(['  '])]
    public function testAddBarcodeRejectsBlankParameter(?string $barcode): void
    {
        $articleId = $this->createArticleDb('Club Mate', 150);

        $this->client->request('POST', "/api/article/{$articleId}/barcode", ['barcode' => $barcode]);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testAddAndListBarcodeOnArticle(): void
    {
        $articleId = $this->createArticleDb('Club Mate', 150);
        $barcode = $this->generateBarcode();

        $afterAdd = $this->requestJson('POST', "/api/article/{$articleId}/barcode", [
            'barcode' => $barcode,
        ], 'article');

        $this->assertCount(1, $afterAdd['barcodes']);
        $this->assertSame($barcode, $afterAdd['barcodes'][0]['barcode']);

        $list = $this->requestJson('GET', "/api/article/{$articleId}/barcode");

        $this->assertSame(1, $list['count']);
        $this->assertSame($barcode, $list['barcodes'][0]['barcode']);
    }

    public function testListArticlesFilteredByBarcode(): void
    {
        // Create two articles with a barcode so we can test the search filter
        $articleId = $this->createArticleDb('Club Mate', 150);
        $otherArticleId = $this->createArticleDb('Flora Mate', 160);
        $barcode = $this->generateBarcode();

        $this->requestJson('POST', "/api/article/{$articleId}/barcode", ['barcode' => $barcode]);
        $this->requestJson('POST', "/api/article/{$otherArticleId}/barcode", ['barcode' => $this->generateBarcode()]);

        $articles = $this->requestJson('GET', '/api/article', ['barcode' => $barcode], 'articles');

        $this->assertSame(1, count($articles));
        $this->assertSame($articleId, $articles[0]['id']);
    }

    public function testDuplicateBarcodeAcrossArticlesIsRejected(): void
    {
        $articleAId = $this->createArticleDb('Club Mate', 150);
        $articleBId = $this->createArticleDb('Flora Mate', 160);
        $barcode = $this->generateBarcode();

        $this->requestJson('POST', "/api/article/{$articleAId}/barcode", ['barcode' => $barcode]);
        $this->client->request('POST', "/api/article/{$articleBId}/barcode", ['barcode' => $barcode]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $listB = $this->requestJson('GET', "/api/article/{$articleBId}/barcode");
        $this->assertSame(0, $listB['count']);
    }

    #[TestWith(['GET']), TestWith(['DELETE'])]
    #[TestDox('$method barcode returns 404 for wrong article')]
    public function testBarcodeOfOtherArticleIsNotAccessible(string $method): void
    {
        $articleAId = $this->createArticleDb('Club Mate', 150);
        $articleBId = $this->createArticleDb('Flora Mate', 160);

        $afterAdd = $this->requestJson('POST', "/api/article/{$articleAId}/barcode", [
            'barcode' => $this->generateBarcode(),
        ], 'article');
        $barcodeId = $afterAdd['barcodes'][0]['id'];

        $this->client->request($method, "/api/article/{$articleBId}/barcode/{$barcodeId}");
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
