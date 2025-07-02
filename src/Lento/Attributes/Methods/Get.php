<?php

namespace Lento\Attributes\Methods;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Get
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
        return 'GET';
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
