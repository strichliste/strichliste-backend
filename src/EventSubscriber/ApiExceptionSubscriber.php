<?php

namespace App\EventSubscriber;

use App\Exception\ApiException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiExceptionSubscriber implements EventSubscriberInterface {

    public function __construct(
        #[Autowire('%kernel.debug%')] private bool $debug = false,
    ) {
    }

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

        $error = [
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
        ];

        // The fully-qualified exception class is an internal detail; expose it
        // only in debug so production responses don't disclose app structure.
        if ($this->debug) {
            $error['class'] = get_class($exception);
        }

        $event->setResponse(new JsonResponse(['error' => $error], $exception->getCode()));
    }
}