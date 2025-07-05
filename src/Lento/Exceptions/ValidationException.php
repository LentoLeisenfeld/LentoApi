<?php
namespace Lento\Exceptions;

class ValidationException extends \Exception
{
    protected array $errors = [];

    public function __construct($message = "Validation failed", array $errors = [], $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /** Returns validation error details */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
