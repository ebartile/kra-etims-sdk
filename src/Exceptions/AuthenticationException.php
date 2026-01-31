<?php

namespace KraEtimsSdk\Exceptions;

use Exception;

class AuthenticationException extends Exception
{
    /**
     * The HTTP status code.
     *
     * @var int
     */
    protected $statusCode;

    /**
     * Create a new AuthenticationException instance.
     *
     * @param string $message
     * @param int $statusCode
     */
    public function __construct($message = "Authentication failed", $statusCode = 401)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }
}