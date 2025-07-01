<?php

namespace Lento\Attributes;

use Attribute;

/**
 * Marks a controller method as deprecated in Swagger documentation.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Deprecated
{
    /**
     * Optional deprecation message or version.
     * @var string|null
     */
    public ?string $message;

    /**
     * @param string|null $message Additional information about the deprecation (e.g. replacement or version).
     */
    public function __construct(?string $message = null)
    {
        $this->message = $message;
    }

    /**
     * Get the deprecation message.
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }
}