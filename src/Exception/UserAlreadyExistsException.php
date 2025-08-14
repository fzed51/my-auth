<?php

declare(strict_types=1);

namespace MyAuth\Exception;

class UserAlreadyExistsException extends UserException
{
    public function __construct(string $email = '')
    {
        $message = empty($email)
            ? 'User already exists'
            : "User with email '{$email}' already exists";

        parent::__construct($message, 409);
    }
}
