<?php

namespace Lento;

use Lento\Routing\Router;
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
    /** @var array<class-string, object> */
    private array $serviceInstances = [];
    /** @var array<class-string> */
    private array $controllers;
    /** @var callable */
    private $dispatcher;
    /** @var callable[] */
    private array $middlewares = [];

    /**
     * @param array<class-string> $controllers
     * @param array<class-string> $services
     * @param string|null $cacheFile Path to route cache file
     */
    public function __construct(array $controllers, array $services, string $cacheFile = null)
    {
        $this->controllers = $controllers;

        if (Env::isDev()) {
            $this->controllers[] = SwaggerController::class;
        }

        $this->initDependencyInjection($services);

        // Router setup with cache
        $this->cacheFile = $cacheFile ?? __DIR__ . '/../cache/lento_routes.php';
        $loaded = Router::loadFromCache($this->cacheFile);
        $this->router = $loaded ?: new Router();
        $this->serviceInstances[Router::class] = $this->router;
        // Register and cache routes if none exist
        if (!is_file($this->cacheFile) || !$this->router->hasRoutes()) {
            $this->registerAttributeRoutes();
            $this->router->cache($this->cacheFile);
        }

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
     * Instantiate service classes once.
     */
    private function initDependencyInjection(array $services): void
    {
        foreach ($services as $cls) {
            $this->serviceInstances[$cls] = new $cls();
        }
    }

    /**
     * Instantiate service classes once.
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Retrieve a service instance.
     * @template T
     * @param class-string<T> $className
     * @return T|null
     */
    public function get(string $className)
    {
        return $this->serviceInstances[$className] ?? null;
    }

    /**
     * Scan controllers for attribute-based routes.
     */
    /**
     * Scan controllers for attribute-based routes, honoring class-level prefix.
     */
    private function registerAttributeRoutes(): void
    {
        foreach ($this->controllers as $className) {
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
     * Resolve handler from cache spec and inject dependencies.
     */
    private function resolveHandler($spec)
    {
        if (is_array($spec)) {
            [$cls, $method] = $spec;
            // instantiate controller
            $instance = new $cls();

            // reflect over all properties
            $rc = new \ReflectionClass($instance);
            foreach ($rc->getProperties() as $prop) {
                foreach ($prop->getAttributes(\Lento\Attributes\Inject::class) as $attr) {
                    $type = $prop->getType()?->getName();
                    if (!$type) {
                        continue;
                    }
                    // Router injection
                    if ($type === \Lento\Routing\Router::class) {
                        $dep = $this->router;
                    }
                    // Service injection
                    elseif (isset($this->serviceInstances[$type])) {
                        $dep = $this->serviceInstances[$type];
                    } else {
                        continue;
                    }
                    $prop->setAccessible(true);
                    $prop->setValue($instance, $dep);
                }
            }

            return [ $instance, $method ];
        }

        return $spec;
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
