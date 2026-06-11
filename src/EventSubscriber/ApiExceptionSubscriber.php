<?php

namespace App\EventSubscriber;

use App\Exception\ApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiExceptionSubscriber implements EventSubscriberInterface {

    static function getSubscribedEvents(): array {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    function onKernelException(ExceptionEvent $event) {

        $exception = $event->getThrowable();

        if (!$exception instanceof ApiException) {
            return;
        }

        // frozen legacy envelope: clients key off `class`, even in prod
        $error = [
            'class' => get_class($exception),
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
        ];

        $event->setResponse(new JsonResponse(['error' => $error], $exception->getCode()));
    }
}