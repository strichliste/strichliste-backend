<?php

namespace App\Mqtt;

/**
 * Immutable MQTT connection settings.
 *
 * Wired from MQTT_* environment variables in config/packages/mqtt.yaml — kept
 * out of the strichliste settings tree on purpose, since that tree is published
 * verbatim (and unauthenticated) by /api/settings and would leak the broker
 * credentials.
 */
final class MqttConfig
{
    public function __construct(
        public readonly bool $enabled,
        public readonly string $host,
        public readonly int $port,
        public readonly string $username,
        public readonly string $password,
        public readonly string $clientId,
        public readonly string $baseTopic,
        public readonly int $qos,
        public readonly bool $retain,
        public readonly bool $tls,
    ) {
    }

    /**
     * Join the base topic with an action sub-topic, e.g. "strichliste" + "user/created".
     */
    public function topicFor(string $subTopic): string
    {
        $base = trim($this->baseTopic, '/');

        return '' === $base ? $subTopic : $base.'/'.$subTopic;
    }
}
