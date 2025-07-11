<?php

namespace Lento;

/**
 * Undocumented class
 */
class MiddlewareRunner
{
    /**
     * Undocumented variable
     *
     * @var array
     */
    private array $global = [];

    /**
     * Undocumented function
     *
     * @param callable $middleware
     * @return void
     */
    public function use(callable $middleware): void
    {
        $this->global[] = $middleware;
    }

    /**
     * Undocumented function
     *
     * @param [type] $request
     * @param [type] $response
     * @param callable $next
     * @return void
     */
    public function handle($request, $response, callable $next): void
    {
        $stack = array_reverse($this->global);

        $runner = array_reduce(
            $stack,
            fn ($next, $middleware) => fn () => $middleware($request, $response, $next),
            $next
        );

        $runner();
    }
}
