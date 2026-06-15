<?php

namespace App\Tests\Controller\Api;

/**
 * The error envelope shape is preserved: class/code/message in legacy key
 * order, `class` present even in prod. Domain errors keep their App\Exception\*
 * discriminator; request-payload/query validation failures surface as
 * App\Exception\ValidationException (422) through the same envelope.
 */
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

    public function testInvalidUtf8NameIsValidationErrorNotA500(): void
    {
        // invalid UTF-8 sanitizes to an empty name → caught as a validation
        // failure (422), never a 500.
        $this->client->request('POST', '/api/user', ['name' => "\xC3\x28"]);
        $this->assertResponseStatusCodeSame(422);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(['class', 'code', 'message'], array_keys($body['error']));
        $this->assertSame(\App\Exception\ValidationException::class, $body['error']['class']);
        $this->assertSame(422, $body['error']['code']);
    }

    public function testMissingRequiredFieldIsValidationError(): void
    {
        // body present but missing the required `name` -> constraint failure (422)
        $this->client->request('POST', '/api/user', ['email' => 'noname@example.com']);
        $this->assertResponseStatusCodeSame(422);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(['class', 'code', 'message'], array_keys($body['error']));
        $this->assertSame(\App\Exception\ValidationException::class, $body['error']['class']);
        $this->assertSame(422, $body['error']['code']);
    }

    public function testEmptyBodyIsEnvelopedNotA500(): void
    {
        // a completely empty body is rejected by the payload resolver; it must
        // still come back as the envelope, never an unhandled 500.
        $this->client->request('POST', '/api/user', []);
        $this->assertResponseStatusCodeSame(400);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(['class', 'code', 'message'], array_keys($body['error']));
        $this->assertSame(\App\Exception\ValidationException::class, $body['error']['class']);
    }
}
