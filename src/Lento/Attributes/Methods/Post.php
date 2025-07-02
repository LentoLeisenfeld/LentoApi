<?php

namespace Lento\Attributes\Methods;

use Attribute;

/**
 * Defines a route for HTTP POST method.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Post
{
    private string $path;

    /**
     * @param string $path The route path pattern (e.g. '/users/{id}').
     */
    public function __construct(string $path = '')
    {
        $this->path = $path;
    }

    public function getHttpMethod(): string
    {
        return 'POST';
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
