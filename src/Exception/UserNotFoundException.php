<?php

declare(strict_types=1);

namespace MyAuth\Exception;

class UserNotFoundException extends UserException
{
    public function __construct(string $identifier = '')
    {
        $message = empty($identifier)
            ? 'User not found'
            : "User not found: {$identifier}";

        parent::__construct($message, 404);
    }
}
