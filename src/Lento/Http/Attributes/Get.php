<?php

namespace Lento\Http\Attributes;

use Attribute;

/**
 *
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Get
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
        return 'GET';
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
