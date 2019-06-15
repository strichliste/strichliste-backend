<?php

namespace App\EventSubscriber;

use App\Exception\ApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiExceptionSubscriber implements EventSubscriberInterface {

    static function getSubscribedEvents() {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    function onKernelException(GetResponseForExceptionEvent $event) {

        $exception = $event->getException();

        if (!$exception instanceof ApiException) {
            return;
        }

        $response = [
            'error' => [
                'class' => get_class($exception),
                'code' => $exception->getCode(),
                'message' => $exception->getMessage()
            ]
        ];

        $event->setResponse(new JsonResponse($response, $exception->getCode()));
    }
}