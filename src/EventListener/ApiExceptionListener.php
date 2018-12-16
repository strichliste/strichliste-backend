<?php

namespace App\EventListener;

use App\Exception\ApiException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

class ApiExceptionListener {

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