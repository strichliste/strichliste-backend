<?php

namespace App\EventSubscriber;

use App\Exception\ApiException;
use App\Exception\ValidationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Funnel request-payload / query-string mapping failures into the legacy
        // envelope so the API surface speaks one error shape. Scoped to /api so
        // the Twig UI's native form/error handling is untouched.
        //   - constraint failures (ValidationFailedException) -> 422
        //   - malformed/empty body, unsupported media type -> the resolver's
        //     own 400/415/422 status, re-wrapped in the envelope
        if (!$exception instanceof ApiException
            && str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            $validationFailure = $this->extractValidationFailure($exception);
            if (null !== $validationFailure) {
                $exception = new ValidationException($this->formatViolations($validationFailure));
            } elseif ($exception instanceof HttpExceptionInterface
                && \in_array($exception->getStatusCode(), [400, 415, 422], true)) {
                $exception = new ValidationException($exception->getMessage(), $exception->getStatusCode());
            }
        }

        if (!$exception instanceof ApiException) {
            return;
        }

        // frozen legacy envelope: clients key off `class`, even in prod
        $error = [
            'class' => $exception::class,
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
        ];

        $event->setResponse(new JsonResponse(['error' => $error], $exception->getCode()));
    }

    private function extractValidationFailure(\Throwable $exception): ?ValidationFailedException
    {
        if ($exception instanceof ValidationFailedException) {
            return $exception;
        }

        $previous = $exception->getPrevious();

        return $previous instanceof ValidationFailedException ? $previous : null;
    }

    private function formatViolations(ValidationFailedException $exception): string
    {
        $parts = [];
        foreach ($exception->getViolations() as $violation) {
            $path = $violation->getPropertyPath();
            $parts[] = ('' !== $path ? $path.': ' : '').$violation->getMessage();
        }

        return implode('; ', $parts) ?: 'Validation failed';
    }
}
