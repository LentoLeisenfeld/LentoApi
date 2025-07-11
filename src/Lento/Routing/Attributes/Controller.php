<?php

namespace Lento\Routing\Attributes;

use Attribute;

/**
 *
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Controller
{
    /**
     * Undocumented variable
     *
     * @var string|null
     */
    private ?string $path;

    /**
     * Undocumented function
     *
     * @param string|null $path Optional prefix path for this controller, e.g. '/hello'
     */
    public function __construct(?string $path = null)
    {
        // Normalize: ensure leading slash, no trailing slash (or null)
        if ($path === null || $path === '') {
            $this->path = null;
        } else {
            $trimmed = trim($path, '/');
            $this->path = '/' . $trimmed;
        }
    }

    /**
     * Get the configured controller path prefix (e.g. '/hello').
     * Returns empty string if none.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path ?? '';
    }
}
