<?php

namespace App\Tests\Controller\Api;

/** The error envelope is frozen: class/code/message in legacy key order, `class` present even in prod. */
class ErrorEnvelopeTest extends AbstractApplicationTestCase
{
    public function testUserNotFoundEnvelope(): void
    {
        $this->client->request('GET', '/api/user/999999');
        $this->assertResponseStatusCodeSame(404);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(
            ['class', 'code', 'message'],
            array_keys($body['error']),
            'Envelope keys must keep the legacy order',
        );
        $this->assertSame(\App\Exception\UserNotFoundException::class, $body['error']['class']);
        $this->assertSame(404, $body['error']['code']);
    }

    public function testInvalidUtf8NameIsA400NotA500(): void
    {
        $this->client->request('POST', '/api/user', ['name' => "\xC3\x28"]);
        $this->assertResponseStatusCodeSame(400);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(\App\Exception\ParameterInvalidException::class, $body['error']['class']);
    }

    public function testParameterMissingEnvelope(): void
    {
        $this->client->request('POST', '/api/user', []);
        $this->assertResponseStatusCodeSame(400);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(\App\Exception\ParameterMissingException::class, $body['error']['class']);
    }
}
