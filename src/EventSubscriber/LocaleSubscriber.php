<?php

namespace App\EventSubscriber;

use App\Service\SettingsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Applies the operator-configured UI language (i18n.language in
 * config/strichliste.yaml) to every request. The kiosk has no per-user
 * accounts, so the locale is a global setting rather than a negotiation —
 * the same setting already drives number formatting in AppExtension.
 */
class LocaleSubscriber implements EventSubscriberInterface {

    public function __construct(private SettingsService $settings) {
    }

    static function getSubscribedEvents(): array {
        return [
            // Priority 20: ahead of Symfony's LocaleListener (16), so the
            // configured locale is in place before anything consumes it.
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void {
        $locale = (string) $this->settings->getOrDefault('i18n.language', 'en');
        if ($locale !== '') {
            $event->getRequest()->setLocale($locale);
        }
    }
}
