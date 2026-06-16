<?php

namespace App\Tests\Controller\Api;

/**
 * Pins the byte-level /api response contract that the typed Response DTOs +
 * #[Serialize] must keep reproducing: exact JSON key order, presence of
 * null-valued keys (legacy clients read them), and JsonResponse-compatible
 * hex-escaping of < > & ' " in strings.
 *
 * The other controller tests json_decode() before asserting, so they cannot
 * catch key reordering, dropped null keys, or a changed string encoder — the
 * three ways a serializer-config change could silently break the frozen shape.
 */
class ResponseContractTest extends AbstractApplicationTestCase
{
    public function testUserKeyOrderNullKeysAndEscaping(): void
    {
        // name carries every character JsonResponse hex-escapes; no email so the
        // null `email` key must still be emitted.
        $name = 'Tom & Jerry <x> "Q" O\'Brien';
        $this->client->request('POST', '/api/user', ['name' => $name]);
        $this->assertResponseIsSuccessful();
        $id = json_decode($this->client->getResponse()->getContent(), true)['user']['id'];

        $this->client->request('GET', "/api/user/{$id}");
        $raw = $this->client->getResponse()->getContent();
        $user = json_decode($raw, true)['user'];

        $this->assertSame(
            ['id', 'name', 'email', 'balance', 'isActive', 'isDisabled', 'created', 'updated'],
            array_keys($user),
            'frozen /api user key order changed',
        );
        $this->assertArrayHasKey('email', $user, 'null email key must be present, not dropped');
        $this->assertNull($user['email']);

        // Legacy $this->json() escaped < > & ' " via JsonResponse::DEFAULT_ENCODING_OPTIONS.
        // The hex-escaped form must appear in the raw bytes and the unescaped name must not.
        $hexEscaped = json_encode($name, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $this->assertStringContainsString($hexEscaped, $raw, 'name is not hex-escaped like JsonResponse');
        $this->assertStringNotContainsString($name, $raw, 'unescaped name leaked into the response');
    }

    public function testTransactionKeyOrderAndNullKeys(): void
    {
        $userId = $this->createUserDb('Contract Tx User');
        // plain deposit: quantity/article/sender/recipient/comment are all null and must stay present
        $tx = $this->requestJson('POST', "/api/user/{$userId}/transaction", ['amount' => 500], 'transaction');

        $this->assertSame(
            ['id', 'user', 'quantity', 'article', 'sender', 'recipient', 'comment', 'amount', 'isDeleted', 'isDeletable', 'created'],
            array_keys($tx),
            'frozen /api transaction key order changed',
        );
        foreach (['quantity', 'article', 'sender', 'recipient', 'comment'] as $key) {
            $this->assertArrayHasKey($key, $tx, "null {$key} key must be present, not dropped");
            $this->assertNull($tx[$key]);
        }
    }

    public function testArticleKeyOrderAndNullPrecursor(): void
    {
        $articleId = $this->createArticleDb('Contract Article', 150);
        $article = $this->requestJson('GET', "/api/article/{$articleId}", [], 'article');

        $this->assertSame(
            ['id', 'name', 'barcodes', 'tags', 'amount', 'isActive', 'usageCount', 'precursor', 'created'],
            array_keys($article),
            'frozen /api article key order changed',
        );
        $this->assertArrayHasKey('precursor', $article, 'null precursor key must be present, not dropped');
        $this->assertNull($article['precursor']);
    }
}
