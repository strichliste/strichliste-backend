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
        $this->assertSame(self::TEST_USER_NAME, $data['user']['name']);
        $this->assertSame(0, $data['user']['balance']);
        $this->assertArrayHasKey('id', $data['user']);
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
}
