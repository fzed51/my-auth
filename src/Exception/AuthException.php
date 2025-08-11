<?php

declare(strict_types=1);

namespace MyAuth\Exception;

use Exception;

class AuthException extends Exception
{
    public function __construct(string $message = 'Authentication failed', int $code = 401)
    {
        parent::__construct($message, $code);
    }
}
