<?php

namespace App\Mqtt;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Immutable MQTT connection settings.
 *
 * Wired straight from MQTT_* environment variables (defaults in
 * config/packages/mqtt.yaml) — kept out of the strichliste settings tree on
 * purpose, since that tree is published verbatim (and unauthenticated) by
 * /api/settings and would leak the broker credentials.
 */
final class MqttConfig
{
    public function __construct(
        #[Autowire(env: 'bool:MQTT_ENABLED')]
        public readonly bool $enabled,
        #[Autowire(env: 'MQTT_HOST')]
        public readonly string $host,
        #[Autowire(env: 'int:MQTT_PORT')]
        public readonly int $port,
        #[Autowire(env: 'MQTT_USERNAME')]
        public readonly string $username,
        #[Autowire(env: 'MQTT_PASSWORD')]
        public readonly string $password,
        #[Autowire(env: 'MQTT_CLIENT_ID')]
        public readonly string $clientId,
        #[Autowire(env: 'MQTT_BASE_TOPIC')]
        public readonly string $baseTopic,
        #[Autowire(env: 'int:MQTT_QOS')]
        public readonly int $qos,
        #[Autowire(env: 'bool:MQTT_RETAIN')]
        public readonly bool $retain,
        #[Autowire(env: 'bool:MQTT_TLS')]
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
