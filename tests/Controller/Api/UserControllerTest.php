<?php

namespace App\Tests\Controller\Api;


class UserControllerTest extends AbstractApplicationTestCase
{
    const TEST_USER_NAME = 'TestUser';

    public function testCreateUser(): void
    {
        $this->client->request('POST', '/api/user', ['name' => self::TEST_USER_NAME]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(self::TEST_USER_NAME, $data['user']['name']);
        $this->assertSame(0, $data['user']['balance']);
        $this->assertArrayHasKey('id', $data['user']);
    }
}
