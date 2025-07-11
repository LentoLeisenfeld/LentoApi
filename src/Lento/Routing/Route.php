<?php

namespace Lento\Routing;

/**
 * Undocumented class
 */
class Route
{
    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $rawPath;

    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $method;

    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $regex;

    /**
     * Undocumented variable
     *
     * @var array
     */
    public array $paramNames;

    /**
     * Undocumented variable
     *
     * @var [type]
     */
    public $handlerSpec;

    /**
     * Undocumented function
     *
     * @param string $method
     * @param string $path
     * @param [type] $handlerSpec
     */
    public function __construct(string $method, string $path, $handlerSpec)
    {
        $this->method = strtoupper($method);
        $this->rawPath = '/' . ltrim(rtrim($path, '/'), '/');
        $this->handlerSpec = $handlerSpec;

        preg_match_all('#\{(\w+)\}#', $this->rawPath, $m);
        $this->paramNames = $m[1];
        $pattern = preg_replace('#\{\w+\}#', '([^/]+)', $this->rawPath);
        $this->regex = '#^' . $pattern . '$#';
    }
}
