<?php

namespace Lento\Routing;

use Lento\Attributes\Param;
use ReflectionClass;
use ReflectionMethod;

class Router {
    private array $routes = [];

    public function addRoute(string $method, string $path, callable $handler, array $middleware = [], ?string $name = null): void {
        $this->routes[] = new Route($method, $path, $handler, $middleware, $name);
    }

    public function dispatch($request, $response): void {
        $uri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
        $httpMethod = $_SERVER['REQUEST_METHOD'];

        foreach ($this->routes as $route) {
            if (strtoupper($route->method) !== $httpMethod) continue;

            [$matched, $vars] = $this->matchPath($route->path, $uri);
            if (!$matched) continue;

            $handler = $route->handler;

            // Apply middleware
            foreach ($route->middleware as $mw) {
                if (!is_callable($mw)) {
                    throw new \Exception("Route middleware is not callable");
                }
                if ($mw($request, $response) === false) {
                    return;
                }
            }

            $inputData = in_array($httpMethod, ['POST', 'PUT'])
                ? json_decode(file_get_contents('php://input'), true) ?? []
                : [];

            // === Handle [Controller::class, 'method'] ===
            if (is_array($handler) && count($handler) === 2 && is_string($handler[1])) {
                [$controllerOrClass, $methodName] = $handler;

                $controllerInstance = is_string($controllerOrClass)
                    ? \Lento\Container::get($controllerOrClass)
                    : $controllerOrClass;

                $refMethod = new ReflectionMethod($controllerInstance, $methodName);
                $params = $this->resolveParams($refMethod, $request, $response, $vars, $inputData);

                $result = call_user_func_array([$controllerInstance, $methodName], $params);

            // === Handle Closure or other callable ===
            } elseif (is_callable($handler)) {
                $result = $handler($request, $response);

            } else {
                throw new \Exception("Invalid route handler: must be [Class, 'method'] or Closure.");
            }

            $response = json_encode($result);
            header('Content-Length: ' . strlen($response));
            header('Connection: keep-alive');
            header('Content-Type: application/json');
            http_response_code(200);
            echo $response;
            return;
        }

        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
    }


    private function resolveParams(ReflectionMethod $refMethod, $request, $response, array $vars, array $inputData): array {
        $params = [];

        foreach ($refMethod->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();
            $paramAttr = $param->getAttributes(Param::class)[0] ?? null;

            if ($name === 'req') {
                $params[] = $request;
            } elseif ($name === 'res') {
                $params[] = $response;
            } elseif ($paramAttr && $type && !$type->isBuiltin()) {
                $dto = new ($type->getName())();
                $refDto = new ReflectionClass($dto);

                foreach ($refDto->getProperties() as $prop) {
                    $propName = $prop->getName();
                    $value = $inputData[$propName] ?? $_GET[$propName] ?? $vars[$propName] ?? null;

                    if ($value !== null) {
                        $prop->setAccessible(true);
                        $prop->setValue($dto, $value);
                    }
                }
                $params[] = $dto;
            } elseif (isset($vars[$name])) {
                $params[] = $vars[$name];
            } else {
                $params[] = null;
            }
        }

        return $params;
    }

    private function matchPath(string $routePath, string $uri): array {
        $pattern = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . rtrim($pattern, '/') . '$#';

        if (preg_match($pattern, $uri, $matches)) {
            $params = array_filter($matches, fn($k) => is_string($k), ARRAY_FILTER_USE_KEY);
            return [true, $params];
        }

        return [false, []];
    }

    public function getRoutes(): array {
        return $this->routes;
    }

    public function generateUrl(string $name, array $params = []): ?string {
        foreach ($this->routes as $route) {
            if ($route->name === $name) {
                $path = $route->path;
                foreach ($params as $key => $val) {
                    $path = str_replace("{{$key}}", $val, $path);
                }
                return $path;
            }
        }
        return null;
    }

    public function loadCachedRoutes(array $controllers): bool {
        if (RouteCache::isAvailable($controllers)) {
            $routeData = RouteCache::load();
            $this->routes = array_map(function ($routeArr) {
                return Route::fromArray($routeArr);
            }, $routeData);
            return true;
        }
        return false;
    }

    public function cacheRoutes(array $controllers): void {
        RouteCache::store($this->routes, $controllers);
    }
}
