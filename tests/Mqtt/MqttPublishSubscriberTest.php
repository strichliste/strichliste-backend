<?php

namespace App\Tests\Mqtt;

use App\Entity\Article;
use App\Entity\User;
use App\Event\ArticleCreatedEvent;
use App\Event\TransactionCreatedEvent;
use App\Event\TransactionRevertedEvent;
use App\Event\UserCreatedEvent;
use App\EventSubscriber\MqttPublishSubscriber;
use App\Mqtt\MqttConfig;
use App\Serializer\ArticleSerializer;
use App\Serializer\TransactionSerializer;
use App\Serializer\UserSerializer;
use App\Service\TransactionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Direct subscriber test: a recording publisher stands in for the broker, real
 * serializers come from the container and entities are persisted so they carry
 * ids/timestamps. No HTTP involved.
 */
class MqttPublishSubscriberTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RecordingPublisher $publisher;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->publisher = new RecordingPublisher();
    }

    private function subscriber(bool $enabled): MqttPublishSubscriber
    {
        $container = static::getContainer();
        $config = new MqttConfig($enabled, 'localhost', 1883, '', '', 'strichliste', 'strichliste', 0, false, false);

        return new MqttPublishSubscriber(
            $config,
            $this->publisher,
            $container->get(UserSerializer::class),
            $container->get(TransactionSerializer::class),
            $container->get(ArticleSerializer::class),
            $container->get(NormalizerInterface::class),
        );
    }

    private function persistUser(string $name): User
    {
        $user = new User();
        $user->setName($name);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function testUserCreatedPublishesSerializedUser(): void
    {
        $user = $this->persistUser('Alice');

        $this->subscriber(true)->onUserCreated(new UserCreatedEvent($user));

        self::assertCount(1, $this->publisher->calls);
        [$topic, $payload] = $this->publisher->calls[0];
        self::assertSame('user/created', $topic);
        self::assertSame($user->getId(), $payload['id']);
        self::assertSame('Alice', $payload['name']);
        self::assertArrayHasKey('balance', $payload);
    }

    public function testTransactionCreatedPublishesSerializedTransaction(): void
    {
        $user = $this->persistUser('Bob');
        $tx = static::getContainer()->get(TransactionService::class)->createForUser($user, 250);

        $this->subscriber(true)->onTransactionCreated(new TransactionCreatedEvent($tx));

        self::assertCount(1, $this->publisher->calls);
        [$topic, $payload] = $this->publisher->calls[0];
        self::assertSame('transaction/created', $topic);
        self::assertSame($tx->getId(), $payload['id']);
        self::assertSame(250, $payload['amount']);
    }

    public function testTransactionRevertedPublishesToDeletedTopic(): void
    {
        $user = $this->persistUser('Dora');
        $service = static::getContainer()->get(TransactionService::class);
        $tx = $service->createForUser($user, 250);
        $reverted = $service->revertTransaction((int) $tx->getId());

        $this->subscriber(true)->onTransactionReverted(new TransactionRevertedEvent($reverted));

        self::assertCount(1, $this->publisher->calls);
        [$topic, $payload] = $this->publisher->calls[0];
        self::assertSame('transaction/deleted', $topic);
        self::assertTrue($payload['isDeleted']);
    }

    public function testArticleCreatedPublishesSerializedArticle(): void
    {
        $article = new Article();
        $article->setName('Mate');
        $article->setAmount(150);
        $this->em->persist($article);
        $this->em->flush();

        $this->subscriber(true)->onArticleCreated(new ArticleCreatedEvent($article));

        self::assertCount(1, $this->publisher->calls);
        [$topic, $payload] = $this->publisher->calls[0];
        self::assertSame('article/created', $topic);
        self::assertSame($article->getId(), $payload['id']);
        self::assertSame(150, $payload['amount']);
    }

    public function testNothingIsPublishedWhenDisabled(): void
    {
        $user = $this->persistUser('Carol');

        $this->subscriber(false)->onUserCreated(new UserCreatedEvent($user));

        self::assertSame([], $this->publisher->calls);
    }
}
