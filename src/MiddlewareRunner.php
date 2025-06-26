<?php

namespace Lento;

class MiddlewareRunner {
    private array $global = [];

    public function use(callable $middleware): void {
        $this->global[] = $middleware;
    }

    public function handle($request, $response, callable $next): void {
        $stack = array_reverse($this->global);

        $runner = array_reduce(
            $stack,
            fn ($next, $middleware) => fn () => $middleware($request, $response, $next),
            $next
        );

        $runner();
    }
    
}
