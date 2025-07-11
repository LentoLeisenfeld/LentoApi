<?php

namespace Lento\Exceptions;

use Exception;
use Throwable;
use Lento\Enums\Message;

/**
 * Thrown when input validation fails.
 */
class ValidationException extends Exception
{
    /**
     * @var array
     */
    protected array $errors = [];

    /**
     * @param string|Message $message
     * @param array $errors
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(
        $message = Message::ValidationFailed->value,
        array $errors = [],
        int $code = 422,
        Throwable $previous = null
    ) {
        if ($message instanceof Message) {
            $message = $message->value;
        }
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Returns validation error details.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
