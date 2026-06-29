<?php

namespace App\Mqtt;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Psr\Log\LoggerInterface;

/**
 * Publishes action payloads to an MQTT broker, one short-lived connection per
 * message (connect → publish → disconnect). At strichliste's action volume that
 * is simpler and more robust than holding a connection open across requests.
 *
 * Publishing is a side-channel: any failure (broker down, auth, timeout) is
 * logged and swallowed so it can never break the user action that triggered it.
 */
final class MqttPublisher implements MqttPublisherInterface
{
    public function __construct(
        private readonly MqttConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publish(string $subTopic, array $payload): void
    {
        if (!$this->config->enabled) {
            return;
        }

        $topic = $this->config->topicFor($subTopic);

        try {
            $message = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

            $client = new MqttClient($this->config->host, $this->config->port, $this->config->clientId, MqttClient::MQTT_3_1_1, null, $this->logger);
            $client->connect($this->connectionSettings(), true);
            $client->publish($topic, $message, $this->config->qos, $this->config->retain);
            $client->disconnect();
        } catch (\Throwable $e) {
            $this->logger->error('MQTT publish failed', ['topic' => $topic, 'exception' => $e]);
        }
    }

    private function connectionSettings(): ConnectionSettings
    {
        $settings = (new ConnectionSettings())
            ->setConnectTimeout(3)
            ->setSocketTimeout(3)
            ->setUseTls($this->config->tls);

        if ('' !== $this->config->username) {
            $settings = $settings
                ->setUsername($this->config->username)
                ->setPassword('' !== $this->config->password ? $this->config->password : null);
        }

        return $settings;
    }
}
