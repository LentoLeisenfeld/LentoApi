<?php

namespace Lento\Exceptions;

use Exception;
use Throwable;

use Lento\Enums\Message;

/**
 * Undocumented class
 */
class ValidationException extends Exception
{
    /**
     * Undocumented variable
     *
     * @var array
     */
    protected array $errors = [];

    /**
     * Undocumented function
     *
     * @param [type] $message
     * @param array $errors
     * @param integer $code
     * @param Throwable|null $previous
     */
    public function __construct(
        $message = Message::ValidationFailed->value,
        array $errors = [],
        $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Returns validation error details
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
