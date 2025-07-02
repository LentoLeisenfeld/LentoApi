<?php

namespace Lento\Routing;

use Lento\Http\Request;
use Lento\Http\Response;
use Lento\Attributes\Service;
/**
 * High-performance HTTP router with precompiled closure-based routing.
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
        $handler = function(array $params, Request $req, Response $res) use ($handlerSpec) {
            [$class, $methodName] = $handlerSpec;
            $controller = new $class();

            // Property injection
            $rc = new \ReflectionClass($controller);
            foreach ($rc->getProperties() as $prop) {
                if ($prop->getAttributes(\Lento\Attributes\Inject::class)) {
                    $type = $prop->getType()?->getName();
                    $prop->setAccessible(true);
                    match ($type) {
                        Request::class => $prop->setValue($controller, $req),
                        Response::class => $prop->setValue($controller, $res),
                        Router::class => $prop->setValue($controller, $this),
                        default => null,
                    };
                }
            }

            // Method parameter injection
            $rm = $rc->getMethod($methodName);
            $args = [];
            foreach ($rm->getParameters() as $param) {
                $t = $param->getType()?->getName();
                if ($t === Request::class) {
                    $args[] = $req;
                } elseif ($t === Response::class) {
                    $args[] = $res;
                } else {
                    $args[] = $params[$param->getName()] ?? null;
                }
            }

            return $rm->invokeArgs($controller, $args);
        };

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

        // Static routes
        if (isset($this->staticRoutes[$m][$path])) {
            return ($this->staticRoutes[$m][$path])([], $req, $res);
        }

        // Dynamic routes
        foreach ($this->dynamicRoutes[$m] ?? [] as [$regex, $handler]) {
            if (preg_match($regex, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return $handler($params, $req, $res);
            }
        }

        return null;
    }

    /**
     * Cache the route specs as PHP code that replays addRoute calls.
     */
    public function cache(string $cacheFile): void
    {
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $code = "<?php\n";
        $code .= "use Lento\\Routing\\Router;\n";
        $code .= "return (function(): Router {\n";
        $code .= "    \$r = new Router();\n";

        // Static routes
        foreach ($this->staticSpecs as $m => $routes) {
            foreach ($routes as $path => $spec) {
                [$cls, $mth] = $spec;
                $code .= sprintf("    \$r->addRoute('%s', '%s', ['%s','%s']);\n",
                    addslashes($m), addslashes($path), $cls, $mth
                );
            }
        }
        // Dynamic routes
        foreach ($this->dynamicSpecs as $m => $entries) {
            foreach ($entries as $entry) {
                [$cls, $mth] = $entry['spec'];
                $code .= sprintf("    \$r->addRoute('%s', '%s', ['%s','%s']);\n",
                    addslashes($m), addslashes($entry['path']), $cls, $mth
                );
            }
        }
        $code .= "    return \$r;\n";
        $code .= "})();\n";

        file_put_contents($cacheFile, $code);
    }

    /**
     * Load Router from cache file.
     */
    public static function loadFromCache(string $cacheFile): ?self
    {
        if (!is_file($cacheFile)) {
            return null;
        }
        $router = include $cacheFile;
        return $router instanceof self ? $router : null;
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
                $list[] = (object)['method'=>$m,'rawPath'=>$path,'handlerSpec'=>$spec];
            }
        }
        foreach ($this->dynamicSpecs as $m => $entries) {
            foreach ($entries as $e) {
                $list[] = (object)['method'=>$m,'rawPath'=>$e['path'],'handlerSpec'=>$e['spec']];
            }
        }
        return $list;
    }
}
