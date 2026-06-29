<?php

namespace App\Tests\Mqtt;

use App\Mqtt\MqttPublisherInterface;

/**
 * Test double that records what would have been published instead of talking to
 * a broker.
 */
final class RecordingPublisher implements MqttPublisherInterface
{
    /** @var list<array{0: string, 1: array<string, mixed>}> */
    public array $calls = [];

    public function publish(string $subTopic, array $payload): void
    {
        $this->calls[] = [$subTopic, $payload];
    }
}
