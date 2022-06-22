<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class BeforeActionSubscriber implements EventSubscriberInterface {

    static function getSubscribedEvents() {
        return [
            KernelEvents::CONTROLLER => 'convertJsonStringToArray',
        ];
    }

    function convertJsonStringToArray(ControllerEvent $event) {
        $request = $event->getRequest();

        if ($request->getContentType() != 'json' || !$request->getContent()) {
            return;
        }

        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestHttpException('invalid json body: ' . json_last_error_msg());
        }

        $request->request->replace(is_array($data) ? $data : array());
    }

}