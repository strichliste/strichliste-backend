<?php

namespace App\EventSubscriber;

use App\Exception\ApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiExceptionSubscriber implements EventSubscriberInterface {
    public static function getSubscribedEvents() {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void {
        $exception = $event->getThrowable();

        if (!$exception instanceof ApiException) {
            return;
        }

        $response = [
            'error' => [
                'class' => $exception::class,
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
            ],
        ];

        $event->setResponse(new JsonResponse($response, $exception->getCode()));
    }
}
