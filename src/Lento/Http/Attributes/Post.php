<?php

namespace Lento\Http\Attributes;

use Attribute;

/**
 * Defines a route for HTTP POST method.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Post
{
    /**
     * Undocumented variable
     *
     * @var string
     */
    private string $path;

    /**
     * Undocumented variable
     *
     * @param string $path The route path pattern (e.g. '/users/{id}').
     */
    public function __construct(string $path = '')
    {
        $this->path = $path;
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function getHttpMethod(): string
    {
        return 'POST';
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }
}
