<?php

namespace Lento\Routing;

use Lento\Attributes\Param;
use Lento\Attributes\Service;
use Lento\Http\{Request, Response};
use ReflectionClass;
use ReflectionMethod;

/**
 * High-performance HTTP router with optimized caching via generated PHP code.
 */
#[Service()]
class Router
{
    private array $staticRoutes = [];
    private array $dynamicRoutes = [];

    /**
     * Add a route with a handler spec (callable or [Class, method] array).
     */
    public function addRoute(string $method, string $path, $handlerSpec): void
    {
        $route = new Route($method, $path, $handlerSpec);
        $m = $route->method;
        if (strpos($route->rawPath, '{') === false) {
            $this->staticRoutes[$m][$route->rawPath] = $route;
        } else {
            $this->dynamicRoutes[$m][] = $route;
        }
    }

    /**
     * Dispatch the request to the matching route.
     */
    public function dispatch(string $uri, string $httpMethod, Request $req, Response $res)
    {
        $uri = rtrim($uri, '/');
        $m = strtoupper($httpMethod);

        // Static routes
        if (isset($this->staticRoutes[$m][$uri])) {
            return $this->invokeHandler($this->staticRoutes[$m][$uri], [], $req, $res);
        }

        // Dynamic routes
        foreach ($this->dynamicRoutes[$m] ?? [] as $route) {
            if (preg_match($route->regex, $uri, $matches)) {
                $params = [];
                foreach ($route->paramNames as $i => $name) {
                    $params[$name] = $matches[$i + 1] ?? null;
                }
                return $this->invokeHandler($route, $params, $req, $res);
            }
        }

        return null;
    }

    /**
     * Invoke a route handler, injecting Request, Response, Router, and params.
     */
    private function invokeHandler(Route $route, array $params, Request $req, Response $res)
    {
        $spec = $route->handlerSpec;
        if (is_array($spec) && count($spec) === 2 && class_exists($spec[0])) {
            [$class, $method] = $spec;
            $controller = new $class();

            // Property injection for #[Inject]
            $rc = new \ReflectionClass($controller);
            foreach ($rc->getProperties() as $prop) {
                foreach ($prop->getAttributes(\Lento\Attributes\Inject::class) as $_) {
                    $prop->setAccessible(true);
                    $type = $prop->getType()?->getName();
                    if ($type === Request::class) {
                        $prop->setValue($controller, $req);
                    } elseif ($type === Response::class) {
                        $prop->setValue($controller, $res);
                    } elseif ($type === self::class) {
                        $prop->setValue($controller, $this);
                    }
                }
            }

            // Method parameter injection
            $rm = new ReflectionMethod($class, $method);
            $args = [];
            foreach ($rm->getParameters() as $p) {
                $t = $p->getType()?->getName();
                if ($t === Request::class) {
                    $args[] = $req;
                } elseif ($t === Response::class) {
                    $args[] = $res;
                } else {
                    $args[] = $params[$p->getName()] ?? null;
                }
            }

            return $rm->invokeArgs($controller, $args);
        }

        // Fallback for closures
        return call_user_func($spec, $params);
    }

    /**
     * Check if any routes are defined.
     */
    public function hasRoutes(): bool
    {
        return !empty($this->staticRoutes) || !empty($this->dynamicRoutes);
    }

    /**
     * Cache the current routes to a PHP file for OPcache-friendly loading.
     */
    public function cache(string $cacheFile): void
    {
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $defs = ['static' => [], 'dynamic' => []];
        foreach ($this->staticRoutes as $m => $routes) {
            foreach ($routes as $path => $route) {
                $spec = $route->handlerSpec;
                if (!is_array($spec)) {
                    continue;
                }
                $defs['static'][$m][$path] = [
                    'path'       => $route->rawPath,
                    'regex'      => $route->regex,
                    'paramNames' => $route->paramNames,
                    'handler'    => $spec,
                ];
            }
        }
        foreach ($this->dynamicRoutes as $m => $routes) {
            foreach ($routes as $route) {
                $spec = $route->handlerSpec;
                if (!is_array($spec)) {
                    continue;
                }
                $defs['dynamic'][$m][] = [
                    'path'       => $route->rawPath,
                    'regex'      => $route->regex,
                    'paramNames' => $route->paramNames,
                    'handler'    => $spec,
                ];
            }
        }
        $export = var_export($defs, true);
        file_put_contents($cacheFile, "<?php return " . $export . ";");
    }

    /**
     * Load routes from generated cache PHP file.
     * Returns null if cache file missing or invalid.
     */
    public static function loadFromCache(string $cacheFile): ?self
    {
        if (!is_file($cacheFile)) {
            return null;
        }
        $raw = @include $cacheFile;
        if (!is_array($raw) || !isset($raw['static'], $raw['dynamic'])) {
            return null;
        }
        $router = new self();
        foreach ($raw['static'] as $m => $routes) {
            foreach ($routes as $path => $def) {
                $router->addRoute($m, $def['path'], $def['handler']);
            }
        }
        foreach ($raw['dynamic'] as $m => $defs) {
            foreach ($defs as $def) {
                $router->addRoute($m, $def['path'], $def['handler']);
            }
        }
        return $router;
    }

    /**
     * Get all registered Route objects.
     *
     * @return Route[]
     */
    public function getRoutes(): array {
        $all = [];
        foreach ($this->staticRoutes as $methodRoutes) {
            foreach ($methodRoutes as $route) {
                $all[] = $route;
            }
        }
        foreach ($this->dynamicRoutes as $methodRoutes) {
            foreach ($methodRoutes as $route) {
                $all[] = $route;
            }
        }
        return $all;
    }
}
