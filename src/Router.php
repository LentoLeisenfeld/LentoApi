<?php
namespace Lento;

use Lento\Attributes\Param;
use ReflectionClass;
use ReflectionMethod;
use Lento\Route;

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

            [$controller, $methodName] = $route->handler;
            $refMethod = new ReflectionMethod($controller, $methodName);

            // Route-spezifische Middleware ausführen
            foreach ($route->middleware as $mw) {
                if (!is_callable($mw)) {
                    throw new \Exception("Route middleware is not callable");
                }
                if ($mw($request, $response) === false) {
                    return;
                }
            }

            // Request-Body auslesen, wenn POST/PUT
            $inputData = in_array($httpMethod, ['POST', 'PUT'])
                ? json_decode(file_get_contents('php://input'), true) ?? []
                : [];

            // Parameter aufbauen
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
                    // DTO-Injection mit #[Param]
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

            // Aufruf
            $result = call_user_func_array([$controller, $methodName], $params);

            header('Content-Type: application/json');
            echo json_encode($result);
            return;
        }

        // Kein Treffer
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
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
}
