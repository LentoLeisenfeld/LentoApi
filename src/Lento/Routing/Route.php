<?php
namespace Lento\Routing;

class Route
{
    public string $method;
    public string $path;
    public $handler; // callable or [object|string, method]
    public array $middleware;
    public ?string $name;

    public function __construct(string $method, string $path, $handler, array $middleware = [], ?string $name = null)
    {
        $this->method = $method;
        $this->path = $path;
        $this->handler = $handler;
        $this->middleware = $middleware;
        $this->name = $name;
    }

    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'path' => $this->path,
            'handler' => [
                is_object($this->handler[0]) ? get_class($this->handler[0]) : $this->handler[0],
                $this->handler[1]
            ],
            'middleware' => $this->middleware,
            'name' => $this->name,
        ];
    }

    public static function fromArray(array $data): self
    {
        $controllerClass = $data['handler'][0];
        $method = $data['handler'][1];

        // Get controller instance from container, NOT a closure
        $controllerInstance = \Lento\Container::get($controllerClass);

        return new self(
            $data['method'],
            $data['path'],
            [$controllerInstance, $method],  // Fixed here: no closure, direct instance
            $data['middleware'] ?? [],
            $data['name'] ?? null
        );
    }
}
