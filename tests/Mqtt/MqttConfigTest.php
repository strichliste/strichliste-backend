<?php

namespace App\Tests\Mqtt;

use App\Mqtt\MqttConfig;
use PHPUnit\Framework\TestCase;

class MqttConfigTest extends TestCase
{
    private function config(string $baseTopic): MqttConfig
    {
        return new MqttConfig(true, 'host', 1883, '', '', 'cid', $baseTopic, 0, false, false);
    }

    public function testTopicForJoinsBaseAndSubTopic(): void
    {
        self::assertSame('strichliste/user/created', $this->config('strichliste')->topicFor('user/created'));
    }

    public function testTopicForTrimsSurroundingSlashesFromBase(): void
    {
        self::assertSame('strichliste/user/created', $this->config('/strichliste/')->topicFor('user/created'));
    }

    public function testTopicForWithEmptyBaseReturnsSubTopicOnly(): void
    {
        self::assertSame('user/created', $this->config('')->topicFor('user/created'));
    }
}
