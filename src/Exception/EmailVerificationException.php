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

class EmailVerificationException extends Exception
{
    /**

     * Constructor

     */

    public function __construct(string $message = 'Email verification error', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
