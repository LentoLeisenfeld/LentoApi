<?php

namespace Lento\Exceptions;

class ForbiddenException extends \Exception
{
    protected $message = 'Forbidden';
    protected $code = 403;

    public function __construct(string $message = null, int $code = 403, \Throwable $previous = null)
    {
        if ($message === null) {
            $message = $this->message;
        }
        parent::__construct($message, $code, $previous);
    }
}
