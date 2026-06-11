<?php

namespace App\EventSubscriber;

use App\Service\SettingsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

// the kiosk has no per-user accounts, so the locale is a global setting (i18n.language), not a negotiation
class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(private SettingsService $settings)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // priority 20: ahead of Symfony's LocaleListener (16)
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $locale = (string) $this->settings->getOrDefault('i18n.language', 'en');
        if ('' !== $locale) {
            $event->getRequest()->setLocale($locale);
        }
    }
}
