<?php

namespace Lento;

use ReflectionClass;

use Lento\Routing\{Router, RouteCache};
use Lento\Routing\Attributes\Controller;

use Lento\Formatter\Attributes\{FileFormatter, JSONFormatter, SimpleXmlFormatter};

use Lento\OpenAPI\OpenAPIController;
use Lento\Http\{Request, Response};

/**
 * Core API class with high-performance routing, middleware, logging, and CORS.
 */
class LentoApi
{
    /**
     * Undocumented variable
     *
     * @var Router
     */
    private Router $router;

    /**
     * Undocumented variable
     *
     * @var string
     */
    private string $cacheFile = '';

    /**
     * Undocumented variable
     *
     * @var callable[]
     */
    private array $middlewares = [];

    /**
     * Undocumented variable
     *
     * @var Container
     */
    private Container $container;

    /**
     * Undocumented function
     *
     * @param array<class-string> $controllers
     * @param array<class-string> $services
     * @param string|null $cacheFile Path to route cache file
     */
    public function __construct(array $controllers, array $services, string $cacheFile = null)
    {
        if (Env::isDev() && OpenAPI::isEnabled()) {
            $controllers[] = OpenAPIController::class;
        }

        $this->initDependencyInjection($services);

        $this->initRouter($controllers, $cacheFile);
    }

    /**
     * Register a middleware to the stack.
     *
     * @param callable $middleware
     * @return self
     */
    public function use(callable $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /**
     * Add simple CORS support via middleware.
     *
     * @param array<string,mixed> $options
     * @return self
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
            if ($req->method === 'OPTIONS') {
                http_response_code(204);
                return $res;
            }
            return $next($req, $res);
        });
    }

    /**
     * Dispatch logic using fast Router.
     *
     * @param Request $req
     * @param Response $res
     * @return Response
     */
    private function handle(Request $req, Response $res): Response
    {
        $this->router->dispatch(
            $req->path,
            $req->method,
            $req,
            $res
        );
        // dispatch already sent/written the response
        return $res;
    }

    /**
     * Instantiate service classes and set up the DI container.
     *
     * @param array $services
     * @return void
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
     *
     * @param array $controllers
     * @param string|null $cacheFile
     * @return void
     */
    private function initRouter(array $controllers, ?string $cacheFile = null): void
    {
        // Set directory if $cacheFile is custom
        if ($cacheFile) {
            RouteCache::setDirectory(dirname($cacheFile));
        }

        $this->cacheFile = $cacheFile ?? RouteCache::getDefaultRouteFile();

        $this->router = new Router();
        $this->router->setContainer($this->container);

        if (RouteCache::isAvailable($controllers)) {
            RouteCache::loadToRouter($this->router);
        } else {
            $this->registerAttributeRoutes($controllers);
            RouteCache::storeFromRouter($this->router, $controllers);
        }
    }

    /**
     * Get the Router instance.
     *
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Retrieve a service instance from the DI container.
     *
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
     *
     * @param [type] $controllers
     * @return void
     */
    private function registerAttributeRoutes($controllers): void
    {
        foreach ($controllers as $className) {
            $rc = new ReflectionClass($className);

            // Class-level prefix via #[Controller('/prefix')]
            $prefix = '';
            foreach ($rc->getAttributes(Controller::class) as $classAttr) {
                $cp = $classAttr->newInstance()->getPath();
                $prefix = $cp !== '' ? '/' . trim($cp, '/') : '';
                break;
            }

            foreach ($rc->getMethods() as $method) {
                $routeAttr = null;
                $formatterAttr = null;

                foreach ($method->getAttributes() as $attr) {
                    $instance = $attr->newInstance();

                    // Routing attribute (Get, Post, etc)
                    if (method_exists($instance, 'getPath') && method_exists($instance, 'getHttpMethod')) {
                        $routeAttr = $instance;
                    }

                    // Formatter attribute
                    if (
                        $instance instanceof FileFormatter ||
                        $instance instanceof SimpleXmlFormatter ||
                        $instance instanceof JSONFormatter
                    ) {
                        $formatterAttr = $instance;
                    }
                }

                if ($routeAttr) {
                    $methodPath = $routeAttr->getPath() ?: $method->getName();
                    $combined = rtrim($prefix, '/') . '/' . ltrim($methodPath, '/');
                    $path = '/' . trim($combined, '/');
                    $this->router->addRoute(
                        $routeAttr->getHttpMethod(),
                        $path,
                        [$className, $method->getName()],
                        $formatterAttr // Can be null
                    );
                }
            }
        }
    }



    /**
     * Default 404 response.
     *
     * @param Request $req
     * @param Response $res
     * @return Response
     */
    private function defaultNotFound(Request $req, Response $res): Response
    {
        http_response_code(404);
        $res->write('404 Not Found');
        return $res;
    }

    /**
     * Start serving requests, applying middleware stack.
     *
     * @return void
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
            fn(Request $req, Response $res) => $this->handle($req, $res)
        );

        // Execute pipeline and capture returned Response
        $finalResponse = $handler($req, $res);

        // Send the final Response to the client
        $finalResponse->send();
    }
}
