<?php

namespace Lento;

use Lento\Routing\{Router, RouteCache};
use Lento\Swagger\SwaggerController;
use Psr\Log\LoggerInterface;
use Lento\Http\{Request, Response};

/**
 * Core API class with high-performance routing, middleware, logging, and CORS.
 */
class LentoApi
{
    /** @var Router */
    private Router $router;

    /** @var string */
    private string $cacheFile = '';

    /** @var callable */
    private $dispatcher;

    /** @var callable[] */
    private array $middlewares = [];

    /** @var Container */
    private Container $container;

    /**
     * @param array<class-string> $controllers
     * @param array<class-string> $services
     * @param string|null $cacheFile Path to route cache file
     */
    public function __construct(array $controllers, array $services, string $cacheFile = null)
    {
        if (Env::isDev()) {
            $controllers[] = SwaggerController::class;
        }

        $this->initDependencyInjection($services);

        $this->initRouter($controllers, $cacheFile);

        // Default dispatcher uses router
        $this->dispatcher = fn(Request $req, Response $res) => $this->handle($req, $res);
    }

    /**
     * Register a middleware to the stack.
     */
    public function use(callable $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /**
     * Enable PSR-3 loggers via middleware.
     * @param LoggerInterface[] $loggers
     */
    public function enableLogging(array $loggers): self
    {
        return $this->use(function (Request $req, Response $res, $next) use ($loggers) {
            foreach ($loggers as $logger) {
                $req->setLogger($logger);
            }
            return $next($req, $res);
        });
    }

    /**
     * Add simple CORS support via middleware.
     * @param array<string,mixed> $options
     */
    public function useCors(array $options): self
    {
        return $this->use(function (Request $req, Response $res, $next) use ($options) {
            foreach (['allowOrigin', 'allowMethods', 'allowHeaders', 'allowCredentials'] as $opt) {
                if (isset($options[$opt])) {
                    $header = str_replace('allow', 'Access-Control-', $opt);
                    $res = $res->withHeader($header, (string) $options[$opt]);
                }
            }
            if ($req->getMethod() === 'OPTIONS') {
                http_response_code(204);
                return $res;
            }
            return $next($req, $res);
        });
    }

    /**
     * Dispatch logic using fast Router.
     */
    private function handle(Request $req, Response $res): Response
    {
        $result = $this->router->dispatch(
            $req->path(),
            $req->getMethod(),
            $req,
            $res
        );
        if ($result !== null) {
            $res->write(json_encode($result));
            return $res;
        }
        return $this->defaultNotFound($req, $res);
    }

    /**
     * Instantiate service classes and set up the DI container.
     */
    private function initDependencyInjection(array $services): void
    {
        $this->container = new Container();
        foreach ($services as $cls) {
            $instance = new $cls();
            $this->container->set($instance);
        }
    }

    /**
     * Router setup with optimized data-only cache.
     */
    private function initRouter($controllers, $cacheFile = null)
    {
        $this->cacheFile = $cacheFile ?? __DIR__ . '/../cache/routes.php';
        $this->router = new Router();
        $this->router->setContainer($this->container);

        if (file_exists($this->cacheFile)) {
            // Load cached routes directly (no reflection, pure data, very fast)
            RouteCache::loadToRouter($this->router);
        } else {
            // Build routes as usual, then store pure-data cache for next time
            $this->registerAttributeRoutes($controllers);
            RouteCache::storeFromRouter($this->router);
        }
    }

    /**
     * Get the Router instance.
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Retrieve a service instance from the DI container.
     * @template T
     * @param class-string<T> $className
     * @return T|null
     */
    public function get(string $className)
    {
        try {
            return $this->container->get($className);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Scan controllers for attribute-based routes, honoring class-level prefix.
     */
    private function registerAttributeRoutes($controllers): void
    {
        foreach ($controllers as $className) {
            $rc = new \ReflectionClass($className);

            // 1) Class-level prefix via #[Controller('/prefix')]
            $prefix = '';
            foreach ($rc->getAttributes(\Lento\Attributes\Controller::class) as $classAttr) {
                $cp = $classAttr->newInstance()->getPath();
                $prefix = $cp !== '' ? '/' . trim($cp, '/') : '';
                break;
            }

            // 2) Iterate over method attributes (Get, Post, etc.)
            foreach ($rc->getMethods() as $method) {
                foreach ($method->getAttributes() as $attr) {
                    $info = $attr->newInstance();

                    // Method-level path or default to method name
                    $methodPath = $info->getPath() ?: $method->getName();

                    // Combine prefix + methodPath, normalize slashes
                    $combined = rtrim($prefix, '/') . '/' . ltrim($methodPath, '/');
                    $path = '/' . trim($combined, '/');

                    // Register the route
                    $this->router->addRoute(
                        $info->getHttpMethod(),
                        $path,
                        [$className, $method->getName()]
                    );
                }
            }
        }
    }

    /**
     * Default 404 response.
     */
    private function defaultNotFound(Request $req, Response $res): Response
    {
        http_response_code(404);
        $res->write('404 Not Found');
        return $res;
    }

    /**
     * Start serving requests, applying middleware stack.
     */
    public function start(): void
    {
        $req = Request::capture();
        $res = new Response();

        // Build middleware + router pipeline
        $handler = array_reduce(
            array_reverse($this->middlewares),
            fn(callable $next, callable $mw): callable =>
            fn(Request $req, Response $res) => $mw($req, $res, $next),
            $this->dispatcher
        );

        // Execute pipeline and capture returned Response
        $finalResponse = $handler($req, $res);

        // Send the final Response to the client
        $finalResponse->send();
    }
}
