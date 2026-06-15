<?php

namespace App\Exception;

/**
 * Raised when request payload / query-string mapping fails validation.
 *
 * Keeps validation failures inside the legacy `{"error": {class, code,
 * message}}` envelope (see {@see \App\EventSubscriber\ApiExceptionSubscriber}),
 * with a 422 status — the machine-readable `class` discriminator stays in the
 * App\Exception\* family that clients key off.
 */
class ValidationException extends ApiException
{
    public function __construct(string $message, int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
