<?php

namespace Lento\Routing;

class Route
{
    public string $rawPath;
    public string $method;
    public string $regex;
    public array $paramNames;
    public $handlerSpec;

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
