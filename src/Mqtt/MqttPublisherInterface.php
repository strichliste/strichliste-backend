<?php

namespace App\Mqtt;

interface MqttPublisherInterface
{
    /**
     * Publish a JSON-encoded payload to "{baseTopic}/{subTopic}".
     *
     * Implementations are fire-and-forget: a broker outage must never propagate
     * into the calling action. Does nothing when MQTT is disabled.
     *
     * @param array<string, mixed> $payload
     */
    public function publish(string $subTopic, array $payload): void;
}
