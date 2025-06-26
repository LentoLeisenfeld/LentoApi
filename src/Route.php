<?php

namespace Lento;

class Route {
    public string $method;
    public string $path;
    public mixed $handler;
    public array $middleware;
    public ?string $name;

    public function __construct(string $method, string $path, $handler, array $middleware = [], ?string $name = null) {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->handler = $handler;
        $this->middleware = $middleware;
        $this->name = $name;
    }
}

