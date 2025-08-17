<?php

/**
 * Exception Class for MyAuth
 *
 * @package MyAuth\Exception
 * @author  MyAuth Team
 */

declare(strict_types=1);

namespace MyAuth\Exception;

use Exception;

/**
 * Exception levée lors de problèmes d'authentification
 */
class AuthenticationException extends Exception
{
    /**

     * Constructor

     */

    public function __construct(
        string $message = 'Authentication failed',
        int $code = 401,
        ?\Throwable $previous = null
    ) {

        parent::__construct($message, $code, $previous);
    }
}
