<?php

namespace Lento\Exceptions;

use Exception;

class UnauthorizedException extends Exception
{
    protected $message = 'Unauthorized';
    protected $code = 401;

    public function __construct(string $message = null, int $code = 401, \Throwable $previous = null)
    {
        if ($message === null) {
            $message = $this->message;
        }
        parent::__construct($message, $code, $previous);
    }
}
