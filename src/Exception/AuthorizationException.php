<?php

declare(strict_types=1);

namespace MyAuth\Exception;

use Exception;

/**
 * Exception levée lors de problèmes d'autorisation
 */
class AuthorizationException extends Exception
{
    public function __construct(string $message = 'Authorization failed', int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
