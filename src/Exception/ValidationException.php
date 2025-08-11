<?php

declare(strict_types=1);

namespace MyAuth\Exception;

use Exception;

class ValidationException extends Exception
{
    private array $errors;

    public function __construct(array $errors, string $message = 'Validation failed', int $code = 400)
    {
        $this->errors = $errors;
        parent::__construct($message, $code);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
