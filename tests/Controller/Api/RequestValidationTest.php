<?php

namespace App\Tests\Controller\Api;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Pins the #[Assert] constraints on the request DTOs: oversize strings (which
 * would silently store on SQLite but error on a real DB) and the transaction
 * field constraints that close the negative-quantity credit exploit and the
 * int-overflow 500. Omitted optional fields must still behave as before.
 */
class RequestValidationTest extends AbstractApplicationTestCase
{
    public function testOversizedArticleNameIsRejected(): void
    {
        $this->client->request('POST', '/api/article', ['name' => str_repeat('a', 256), 'amount' => 150]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(\App\Exception\ValidationException::class, $this->errorClass());
    }

    public function testArticleNameAtLimitIsAccepted(): void
    {
        $this->client->request('POST', '/api/article', ['name' => str_repeat('a', 255), 'amount' => 150]);
        $this->assertResponseIsSuccessful();
    }

    public function testOversizedBarcodeIsRejected(): void
    {
        $articleId = $this->createArticleDb('Club Mate', 150);
        $this->client->request('POST', "/api/article/{$articleId}/barcode", ['barcode' => str_repeat('1', 33)]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testOversizedTagIsRejected(): void
    {
        $articleId = $this->createArticleDb('Club Mate', 150);
        $this->client->request('POST', "/api/article/{$articleId}/tag", ['tag' => str_repeat('t', 256)]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testNegativeQuantityIsRejectedAndDoesNotCredit(): void
    {
        $userId = $this->createUserDb('Eve');
        $articleId = $this->createArticleDb('Club Mate', 150);

        // The exploit: a negative quantity used to compute amount = 150 * -2 * -1 = +300,
        // crediting the buyer. It must now be a 422 and leave the balance untouched.
        $this->client->request('POST', "/api/user/{$userId}/transaction", ['articleId' => $articleId, 'quantity' => -2]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertUserBalance($userId, 0);
    }

    public function testHugeQuantityIsRejectedNotA500(): void
    {
        $userId = $this->createUserDb('Mallory');
        $articleId = $this->createArticleDb('Club Mate', 150);

        $this->client->request('POST', "/api/user/{$userId}/transaction", ['articleId' => $articleId, 'quantity' => PHP_INT_MAX]);
        $this->assertResponseStatusCodeSame(422);
    }

    #[DataProvider('nonPositiveIds')]
    public function testNonPositiveArticleIdIsRejected(int $articleId): void
    {
        $userId = $this->createUserDb('Trent'.$articleId);
        $this->client->request('POST', "/api/user/{$userId}/transaction", ['articleId' => $articleId]);
        $this->assertResponseStatusCodeSame(422);
    }

    #[DataProvider('nonPositiveIds')]
    public function testNonPositiveRecipientIdIsRejected(int $recipientId): void
    {
        $userId = $this->createUserDb('Peggy'.$recipientId);
        $this->client->request('POST', "/api/user/{$userId}/transaction", ['amount' => -100, 'recipientId' => $recipientId]);
        $this->assertResponseStatusCodeSame(422);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function nonPositiveIds(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    public function testMissingAmountTransactionIsEnvelopedNotA500(): void
    {
        $userId = $this->createUserDb('Faythe');

        // body with neither amount nor articleId hit setAmount(null) -> raw TypeError (500).
        // It must now come back as the domain error envelope (400), never an unhandled 500.
        $this->client->request('POST', "/api/user/{$userId}/transaction", ['comment' => 'oops']);
        $this->assertResponseStatusCodeSame(400);
        $this->assertSame(\App\Exception\TransactionInvalidException::class, $this->errorClass());
        $this->assertUserBalance($userId, 0);
    }

    public function testOmittedOptionalFieldsStillWork(): void
    {
        $userId = $this->createUserDb('Walter');
        $articleId = $this->createArticleDb('Club Mate', 150);

        // plain deposit: no quantity/articleId/recipientId -> still 200
        $deposit = $this->requestJson('POST', "/api/user/{$userId}/transaction", ['amount' => 500], 'transaction');
        $this->assertSame(500, $deposit['amount']);

        // purchase without quantity -> service defaults to 1 (amount -150), still 200
        $purchase = $this->requestJson('POST', "/api/user/{$userId}/transaction", ['articleId' => $articleId], 'transaction');
        $this->assertSame(-150, $purchase['amount']);
        $this->assertSame(1, $purchase['quantity']);
    }

    private function errorClass(): string
    {
        return json_decode($this->client->getResponse()->getContent(), true)['error']['class'];
    }
}
