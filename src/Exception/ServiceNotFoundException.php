<?php

declare(strict_types=1);

namespace MyAuth\Exception;

use Exception;

/**
 * Exception levée lorsqu'un service n'est pas trouvé
 */
class ServiceNotFoundException extends Exception
{
    public function __construct(string $message = 'Service not found', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
