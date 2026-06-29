<?php

namespace App\EventSubscriber;

use App\Event\ArticleCreatedEvent;
use App\Event\ArticleDeletedEvent;
use App\Event\ArticleUpdatedEvent;
use App\Event\TransactionCreatedEvent;
use App\Event\TransactionRevertedEvent;
use App\Event\UserCreatedEvent;
use App\Event\UserUpdatedEvent;
use App\Mqtt\MqttConfig;
use App\Mqtt\MqttPublisherInterface;
use App\Serializer\ArticleSerializer;
use App\Serializer\TransactionSerializer;
use App\Serializer\UserSerializer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Bridges domain-action events onto MQTT topics. Each action publishes the same
 * resource representation the /api returns, JSON-encoded, to
 * "{baseTopic}/{entity}/{action}".
 *
 * This is the single place that knows MQTT exists; the domain code only emits
 * plain events.
 */
final class MqttPublishSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MqttConfig $config,
        private readonly MqttPublisherInterface $publisher,
        private readonly UserSerializer $userSerializer,
        private readonly TransactionSerializer $transactionSerializer,
        private readonly ArticleSerializer $articleSerializer,
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserCreatedEvent::class => 'onUserCreated',
            UserUpdatedEvent::class => 'onUserUpdated',
            TransactionCreatedEvent::class => 'onTransactionCreated',
            TransactionRevertedEvent::class => 'onTransactionReverted',
            ArticleCreatedEvent::class => 'onArticleCreated',
            ArticleUpdatedEvent::class => 'onArticleUpdated',
            ArticleDeletedEvent::class => 'onArticleDeleted',
        ];
    }

    public function onUserCreated(UserCreatedEvent $event): void
    {
        $this->emit('user/created', fn () => $this->userSerializer->serialize($event->user));
    }

    public function onUserUpdated(UserUpdatedEvent $event): void
    {
        $this->emit('user/updated', fn () => $this->userSerializer->serialize($event->user));
    }

    public function onTransactionCreated(TransactionCreatedEvent $event): void
    {
        $this->emit('transaction/created', fn () => $this->transactionSerializer->serialize($event->transaction));
    }

    public function onTransactionReverted(TransactionRevertedEvent $event): void
    {
        $this->emit('transaction/deleted', fn () => $this->transactionSerializer->serialize($event->transaction));
    }

    public function onArticleCreated(ArticleCreatedEvent $event): void
    {
        $this->emit('article/created', fn () => $this->articleSerializer->serialize($event->article));
    }

    public function onArticleUpdated(ArticleUpdatedEvent $event): void
    {
        $this->emit('article/updated', fn () => $this->articleSerializer->serialize($event->article));
    }

    public function onArticleDeleted(ArticleDeletedEvent $event): void
    {
        $this->emit('article/deleted', fn () => $this->articleSerializer->serialize($event->article));
    }

    /**
     * @param callable(): object $serialize produces the resource DTO; only
     *                                      invoked when MQTT is enabled, so a
     *                                      disabled integration costs nothing
     */
    private function emit(string $subTopic, callable $serialize): void
    {
        if (!$this->config->enabled) {
            return;
        }

        /** @var array<string, mixed> $payload */
        $payload = $this->normalizer->normalize($serialize());

        $this->publisher->publish($subTopic, $payload);
    }
}
