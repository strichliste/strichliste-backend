<?php

namespace App\Tests\Controller\Api;

class UserControllerTest extends AbstractApplicationTestCase
{
    public const TEST_USER_NAME = 'TestUser';

    public function testCreateUser(): void
    {
        $this->client->request('POST', '/api/user', ['name' => self::TEST_USER_NAME]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(self::TEST_USER_NAME, $data['name']);
        $this->assertSame(0, $data['balance']);
        $this->assertArrayHasKey('id', $data);
    }

    public function testCreateUserAcceptsJsonBody(): void
    {
        // legacy clients (Android/kiosk) send JSON; #[MapRequestPayload] must
        // accept it as well as the form-encoded bodies the other tests use.
        $this->client->request(
            'POST',
            '/api/user',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'JsonUser', 'email' => 'json@example.com']),
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('JsonUser', $data['name']);
        $this->assertSame('json@example.com', $data['email']);
    }

    public function testMalformedJsonBodyIsEnvelopedNotA500(): void
    {
        $this->client->request(
            'POST',
            '/api/user',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{ this is not json',
        );

        $this->assertResponseStatusCodeSame(400);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(['class', 'code', 'message'], array_keys($body['error']));
        $this->assertSame(\App\Exception\ValidationException::class, $body['error']['class']);
    }

    public function testSearchFindsUserByPartialName(): void
    {
        $this->requestJson('POST', '/api/user', ['name' => 'searchme']);

        $result = $this->requestJson('GET', '/api/user/search', ['query' => 'archm', 'limit' => 10]);

        $this->assertSame(1, $result['count']);
        $this->assertSame('searchme', $result['users'][0]['name']);
    }

    public function testApiResponsesAreNotCached(): void
    {
        $this->client->request('GET', '/api/user');

        $this->assertResponseIsSuccessful();
        $cacheControl = $this->client->getResponse()->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
    }

    public function testUserListIsUsersKeyWithoutCount(): void
    {
        $this->requestJson('POST', '/api/user', ['name' => 'listed']);

        $data = $this->requestJson('GET', '/api/user');

        // legacy contract: the user list is { "users": [...] } with NO count field,
        // unlike every other list. Pinned here since it now lives in a bare array.
        $this->assertArrayHasKey('users', $data);
        $this->assertArrayNotHasKey('count', $data);
    }
}
