<?php

namespace Lento\Exceptions;

use Exception;

class NotFoundException extends Exception
{
    protected $message = 'Resource not found';
    protected $code = 404;

    public function __construct(string $message = null, int $code = 404, \Throwable $previous = null)
    {
        if ($message === null) {
            $message = $this->message;
        }
        parent::__construct($message, $code, $previous);
    }
}
