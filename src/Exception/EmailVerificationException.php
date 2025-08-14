<?php

declare(strict_types=1);

namespace MyAuth\Exception;

use Exception;

class EmailVerificationException extends Exception
{
    public function __construct(string $message = 'Email verification error', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
