<?php

namespace App\Tests\Mqtt;

use App\Mqtt\MqttConfig;
use App\Mqtt\MqttPublisher;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class MqttPublisherTest extends TestCase
{
    public function testDisabledPublisherDoesNothing(): void
    {
        $logger = $this->recordingLogger();
        // a bogus host would blow up if a disabled publisher tried to connect
        $config = new MqttConfig(false, 'broker.invalid', 1883, '', '', 'cid', 'strichliste', 0, false, false);

        new MqttPublisher($config, $logger)->publish('user/created', ['id' => 1]);

        self::assertSame([], $logger->records, 'a disabled publisher must not connect or log');
    }

    public function testBrokerFailureIsSwallowedAndLogged(): void
    {
        $logger = $this->recordingLogger();
        // 127.0.0.1:1 refuses the connection immediately — the failure must not surface
        $config = new MqttConfig(true, '127.0.0.1', 1, '', '', 'cid', 'strichliste', 0, false, false);

        new MqttPublisher($config, $logger)->publish('user/created', ['id' => 1]);

        self::assertContains('MQTT publish failed', array_column($logger->records, 'message'));
    }

    /**
     * @return AbstractLogger&object{records: list<array{level: mixed, message: string}>}
     */
    private function recordingLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message];
            }
        };
    }
}
