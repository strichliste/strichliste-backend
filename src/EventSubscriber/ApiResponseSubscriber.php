<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;


class ApiResponseSubscriber implements EventSubscriberInterface {

    function onKernelResponse(ResponseEvent $event) {
        // Only the JSON API must be uncacheable. Stamping no-store on the
        // server-rendered Twig UI would defeat the browser back/forward cache
        // and Turbo's page reuse, so leave those responses to framework defaults.
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $response = $event->getResponse();

        $response->headers->addCacheControlDirective('no-cache', true);
        $response->headers->addCacheControlDirective('max-age', 0);
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->headers->addCacheControlDirective('no-store', true);
    }

     static function getSubscribedEvents() {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }
}