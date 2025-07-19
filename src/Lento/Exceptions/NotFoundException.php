<?php

namespace Lento\Exceptions;

use Exception;
use Throwable;
use Psr\Container\NotFoundExceptionInterface;
use Lento\Enums\Message;

/**
 * Undocumented class
 */
class NotFoundException extends Exception implements NotFoundExceptionInterface
{
    /**
     * Undocumented variable
     *
     * @var [type]
     */
    protected $message = Message::NotFound->value;

    /**
     * Undocumented variable
     *
     * @var integer
     */
    protected $code = 404;

    /**
     * Undocumented function
     *
     * @param string|null $message
     * @param integer $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = null, int $code = 404, Throwable $previous = null)
    {
        if ($message === null) {
            $message = $this->message;
        }
        parent::__construct($message, $code, $previous);
    }
}
