<?php

namespace Lento\Exceptions;

use Exception;
use Throwable;
use Lento\Enums\Message;

/**
 * Undocumented class
 */
class UnauthorizedException extends Exception
{
    /**
     * Undocumented variable
     *
     * @var [type]
     */
    protected $message = Message::Unauthorized->value;

    /**
     * Undocumented variable
     *
     * @var integer
     */
    protected $code = 401;

    /**
     * Undocumented function
     *
     * @param string|null $message
     * @param integer $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = null, int $code = 401, Throwable $previous = null)
    {
        if ($message === null) {
            $message = $this->message;
        }
        parent::__construct($message, $code, $previous);
    }
}
