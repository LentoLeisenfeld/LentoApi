<?php

namespace Lento\Routing;

use Lento\Http\Request;
use Lento\Http\Response;
use Lento\Attributes\Service;
use Lento\Container;

/**
 * High-performance HTTP router with precompiled closure-based routing and DI container support.
 */
#[Service]
class Router
{
    /** @var array<string,callable[]> */
    private array $staticRoutes = [];
    /** @var array<string,array{0:string,1:callable}[]> */
    private array $dynamicRoutes = [];
    /** @var array<string,array<string,array{0:string,1:string}>> */
    private array $staticSpecs = [];
    /** @var array<string,array{path:string,spec:array{0:string,1:string}>[]> */
    private array $dynamicSpecs = [];

    private ?Container $container = null;

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    /**
     * Add a new route and compile its handler closure.
     * @param string $method HTTP method
     * @param string $path Route path (with placeholders)
     * @param array{0:string,1:string} $handlerSpec [ControllerClass, methodName]
     */
    public function addRoute(string $method, string $path, array $handlerSpec): void
    {
        // Normalize
        $normalized = '/' . ltrim(rtrim($path, '/'), '/');
        $m = strtoupper($method);

        // Store spec for caching and Swagger
        if (strpos($normalized, '{') === false) {
            $this->staticSpecs[$m][$normalized] = $handlerSpec;
        } else {
            $this->dynamicSpecs[$m][] = ['path' => $normalized, 'spec' => $handlerSpec];
        }

        // Create handler closure
        $handler = $this->makeHandler($handlerSpec);

        // Store compiled handler
        if (isset($this->staticSpecs[$m][$normalized])) {
            $this->staticRoutes[$m][$normalized] = $handler;
        } else {
            $pattern = preg_replace('#\{(\w+)\}#', '(?P<\\1>[^/]+)', $normalized);
            $regex = '#^' . $pattern . '$#';
            $this->dynamicRoutes[$m][] = [$regex, $handler];
        }
    }

    /**
     * Dispatch the incoming request to the matched route.
     * @return mixed|null
     */
    public function dispatch(string $uri, string $httpMethod, Request $req, Response $res)
    {
        $path = '/' . ltrim(rtrim($uri, '/'), '/');
        $m = strtoupper($httpMethod);

        $handler = $this->staticRoutes[$m][$path] ?? null;
        if (!$handler) {
            // Try dynamic routes...
            foreach ($this->dynamicRoutes[$m] ?? [] as [$regex, $handlerCandidate]) {
                if (preg_match($regex, $path, $matches)) {
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    $handler = fn($req, $res) => $handlerCandidate($params, $req, $res);
                    break;
                }
            }
        } else {
            $handler = fn($req, $res) => $handler([], $req, $res);
        }

        if (!$handler) {
            // 404 Not Found
            $res->status(404)->write('Not found')->send();
            return;
        }

        $result = $handler($req, $res);

        // --- RESPONSE HANDLING ---
        if ($result instanceof Response) {
            $result->send();
        } elseif (is_string($result)) {
            $res->write($result)->send();
        } elseif (is_array($result) || is_object($result)) {
            $res->withHeader('Content-Type', 'application/json')
                ->write(json_encode($result))
                ->send();
        } elseif (is_null($result)) {
            $res->status(204)->send();
        } else {
            $res->write((string) $result)->send();
        }
    }

    /**
     * Pure-data cache for routes.
     */
    public function exportCacheData(): array
    {
        return [
            'staticSpecs' => $this->staticSpecs,
            'dynamicSpecs' => $this->dynamicSpecs,
        ];
    }

    public function importCacheData(array $data): void
    {
        // Static routes
        foreach ($data['staticSpecs'] ?? [] as $method => $routes) {
            foreach ($routes as $path => $spec) {
                $this->staticSpecs[$method][$path] = $spec;
                $this->staticRoutes[$method][$path] = $this->makeHandler($spec);
            }
        }
        // Dynamic routes
        foreach ($data['dynamicSpecs'] ?? [] as $method => $entries) {
            foreach ($entries as $entry) {
                $this->dynamicSpecs[$method][] = $entry;
                $pattern = preg_replace('#\{(\w+)\}#', '(?P<\\1>[^/]+)', $entry['path']);
                $regex = '#^' . $pattern . '$#';
                $this->dynamicRoutes[$method][] = [$regex, $this->makeHandler($entry['spec'])];
            }
        }
    }

    /**
     * Precompiled handler generator using the DI container.
     */
    private function makeHandler(array $handlerSpec): callable
    {
        [$class, $methodName] = $handlerSpec;
        return function (array $params, $req, $res) use ($class, $methodName) {
            // Use container for controller instantiation if available
            $controller = $this->container
                ? $this->container->get($class)
                : new $class();

            // Property injection (with service support)
            $rc = new \ReflectionClass($controller);
            foreach ($rc->getProperties() as $prop) {
                if ($prop->getAttributes(\Lento\Attributes\Inject::class)) {
                    $type = $prop->getType()?->getName();
                    $prop->setAccessible(true);
                    if ($type === \Lento\Http\Request::class) {
                        $prop->setValue($controller, $req);
                    } elseif ($type === \Lento\Http\Response::class) {
                        $prop->setValue($controller, $res);
                    } elseif ($type === self::class) {
                        $prop->setValue($controller, $this);
                    } elseif ($this->container && $type && class_exists($type)) {
                        try {
                            $service = $this->container->get($type);
                            $prop->setValue($controller, $service);
                        } catch (\Throwable $e) {
                            // Could log missing service or ignore
                        }
                    }
                }
            }

            // Parameter injection (with #[Param] support)
            $rm = $rc->getMethod($methodName);
            $args = [];
            foreach ($rm->getParameters() as $param) {
                $t = $param->getType()?->getName();

                if ($t === \Lento\Http\Request::class) {
                    $args[] = $req;
                } elseif ($t === \Lento\Http\Response::class) {
                    $args[] = $res;
                } else {
                    // Check all parameter attributes
                    $paramAttr =
                        $param->getAttributes(\Lento\Attributes\Param::class)[0] ??
                        $param->getAttributes(\Lento\Attributes\Route::class)[0] ??
                        $param->getAttributes(\Lento\Attributes\Query::class)[0] ??
                        $param->getAttributes(\Lento\Attributes\Body::class)[0] ?? null;

                    // Determine source and name
                    if ($paramAttr) {
                        $attrName = $paramAttr->getName();
                        $attrInstance = $paramAttr->newInstance();
                        $key = $attrInstance->name ?? $param->getName();

                        // Map class name to source
                        $source = match ($attrName) {
                            \Lento\Attributes\Param::class => $attrInstance->source ?? 'route',
                            \Lento\Attributes\Route::class => 'route',
                            \Lento\Attributes\Query::class => 'query',
                            \Lento\Attributes\Body::class => 'body',
                            default => 'route',
                        };

                        switch ($source) {
                            case 'route':
                                $args[] = $params[$key] ?? null;
                                break;
                            case 'query':
                                $args[] = $req->query($key);
                                break;
                            case 'body':
                                $paramType = $param->getType()?->getName();
                                $bodyData = $req->body();
                                if (class_exists($paramType) && is_array($bodyData)) {
                                    $args[] = new $paramType($bodyData);
                                } else {
                                    $args[] = $bodyData[$key] ?? null;
                                }
                                break;
                            default:
                                $args[] = null;
                        }
                    } else {
                        $args[] = null;
                    }
                }
            }

            return $rm->invokeArgs($controller, $args);
        };
    }

    /**
     * Check if any routes are defined.
     */
    public function hasRoutes(): bool
    {
        return !empty($this->staticSpecs) || !empty($this->dynamicSpecs);
    }

    /**
     * Get all registered routes for Swagger.
     * @return array{method:string, rawPath:string, handlerSpec:array}[]
     */
    public function getRoutes(): array
    {
        $list = [];
        foreach ($this->staticSpecs as $m => $routes) {
            foreach ($routes as $path => $spec) {
                $list[] = (object) ['method' => $m, 'rawPath' => $path, 'handlerSpec' => $spec];
            }
        }
        foreach ($this->dynamicSpecs as $m => $entries) {
            foreach ($entries as $e) {
                $list[] = (object) ['method' => $m, 'rawPath' => $e['path'], 'handlerSpec' => $e['spec']];
            }
        }
        return $list;
    }
}
