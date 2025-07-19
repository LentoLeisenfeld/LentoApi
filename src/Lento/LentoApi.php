<?php

namespace Lento;

use Lento\Routing\Router;
use Lento\OpenAPI\OpenAPIController;
use Lento\Http\{Request, Response};

/**
 * Core API class with high-performance routing, middleware, logging, and CORS.
 */
class LentoApi
{
    private Router $router;
    private array $middlewares = [];
    private Container $container;

    /**
     * @param array<class-string> $controllers
     */
    public function __construct(array $controllers)
    {
        if (Env::isDev() && OpenAPI::isEnabled()) {
            $controllers[] = OpenAPIController::class;
        }

        // 1. Discover all required classes (controllers + services) for DI
        $allClasses = $this->discoverAllClasses($controllers);

        // 2. Build DI container for all services
        $this->initDependencyInjection($allClasses);

        // 3. Boot router with full cold/warm logic and container
        $this->router = Router::boot($controllers, $allClasses, $this->container);
    }

    public function use(callable $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function useCors(array $options): self
    {
        return $this->use(function (Request $req, Response $res, $next) use ($options) {
            foreach (['allowOrigin', 'allowMethods', 'allowHeaders', 'allowCredentials'] as $opt) {
                if (isset($options[$opt])) {
                    $header = str_replace('allow', 'Access-Control-', $opt);
                    $res = $res->withHeader($header, (string) $options[$opt]);
                }
            }
            if ($req->method === 'OPTIONS') {
                http_response_code(204);
                return $res;
            }
            return $next($req, $res);
        });
    }

    private function handle(Request $req, Response $res): Response
    {
        $this->router->dispatch(
            $req->path,
            $req->method,
            $req,
            $res
        );
        return $res;
    }

    /**
     * Instantiate all required service classes for the DI container.
     *
     * @param array $services
     */
    private function initDependencyInjection(array $allClasses): void
    {
        $this->container = new Container();
        foreach ($allClasses as $cls) {
            if (!class_exists($cls)) continue;
            $instance = new $cls();
            $this->container->set($instance);
        }
    }

    /**
     * Recursively discover all classes (controllers + injected services).
     * @param string[] $controllers
     * @return string[]
     */
    private function discoverAllClasses(array $controllers): array
    {
        $allClasses = $controllers;
        $queue = $controllers;
        $discovered = [];

        while ($queue) {
            $class = array_shift($queue);
            if (!class_exists($class) || isset($discovered[$class])) {
                continue;
            }
            $discovered[$class] = true;

            $rc = new \ReflectionClass($class);

            // Scan #[Inject] properties
            foreach ($rc->getProperties() as $prop) {
                foreach ($prop->getAttributes(\Lento\Routing\Attributes\Inject::class) as $attr) {
                    $type = $prop->getType()?->getName();
                    if ($type && !in_array($type, $allClasses, true)) {
                        $allClasses[] = $type;
                        $queue[] = $type;
                    }
                }
            }

            // Scan #[Inject] constructor params
            $constructor = $rc->getConstructor();
            if ($constructor) {
                foreach ($constructor->getParameters() as $param) {
                    foreach ($param->getAttributes(\Lento\Routing\Attributes\Inject::class) as $attr) {
                        $type = $param->getType()?->getName();
                        if ($type && !in_array($type, $allClasses, true)) {
                            $allClasses[] = $type;
                            $queue[] = $type;
                        }
                    }
                }
            }
        }
        return $allClasses;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function get(string $className)
    {
        try {
            return $this->container->get($className);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Start serving requests, applying middleware stack.
     */
    public function run(): void
    {
        $req = Request::capture();
        $res = new Response();

        $handler = array_reduce(
            array_reverse($this->middlewares),
            fn(callable $next, callable $mw): callable =>
                fn(Request $req, Response $res) => $mw($req, $res, $next),
            fn(Request $req, Response $res) => $this->handle($req, $res)
        );

        $finalResponse = $handler($req, $res);
        $finalResponse->send();
    }
}
