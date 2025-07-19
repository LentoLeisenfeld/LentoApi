<?php

namespace Lento\Routing;

use Lento\Container;
use Lento\Http\{Request, Response, View};
use Lento\Validation\Validator;
use Lento\Exceptions\ValidationException;
use ReflectionClass;
use Lento\Cache;
use Lento\Routing\Attributes\{Inject, Body, Param, Query, Controller as ControllerAttr};
use Lento\Formatter\Attributes\{FileFormatter, JSONFormatter, SimpleXmlFormatter};

class Router
{
    public array $staticRoutes = [];
    public array $dynamicRoutes = [];
    private ?Container $container = null;

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    public function addCompiledRoute(array $plan, bool $dynamic): void
    {
        if ($dynamic) {
            $this->dynamicRoutes[$plan['httpMethod']][] = $plan;
        } else {
            $this->staticRoutes[$plan['httpMethod']][$plan['path']] = $plan;
        }
    }

    public function exportCompiledPlans(): array
    {
        return [
            'staticRoutes' => $this->staticRoutes,
            'dynamicRoutes' => $this->dynamicRoutes,
        ];
    }

    public function importCompiledPlans(array $data): void
    {
        $this->staticRoutes = $data['staticRoutes'] ?? [];
        $this->dynamicRoutes = $data['dynamicRoutes'] ?? [];
    }

    /**
     * One-call, no-brainer router boot: handles cold boot and cache logic.
     */
    public static function boot(array $controllers, array $serviceClasses = [], ?Container $container = null): self
    {
        $router = new self();
        if ($container) {
            $router->setContainer($container);
        }

        if (!Cache::isAvailable($controllers)) {
            // ---- COLD BOOT ----
            $plans = self::compileRoutePlans($controllers);

            foreach ($plans['staticRoutes'] as $method => $routes) {
                foreach ($routes as $path => $plan) {
                    $router->addCompiledRoute($plan, false);
                }
            }
            foreach ($plans['dynamicRoutes'] as $method => $routes) {
                foreach ($routes as $plan) {
                    $router->addCompiledRoute($plan, true);
                }
            }
            Cache::storeFromRouter($router, $controllers, $serviceClasses);
        } else {
            // ---- WARM BOOT ----
            Cache::loadToRouter($router);
        }
        return $router;
    }

    public function dispatch(string $uri, string $httpMethod, Request $req, Response $res)
    {
        $path = '/' . ltrim(rtrim($uri, '/'), '/');
        $m = strtoupper($httpMethod);
        $route = $this->staticRoutes[$m][$path] ?? null;
        $params = [];

        if (!$route) {
            foreach ($this->dynamicRoutes[$m] ?? [] as $entry) {
                if (preg_match($entry['regex'], $path, $matches)) {
                    foreach ($matches as $k => $v)
                        if (is_string($k))
                            $params[$k] = $v;
                    $route = $entry;
                    break;
                }
            }
        }

        if (!$route) {
            return $res->status(404)
                ->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'Not found']))
                ->send();
        }

        $class = $route['controller'];
        $controller = $this->container ? $this->container->get($class) : new $class();

        // Use ReflectionProperty for DI (supports private/protected)
        foreach ($route['propInject'] as $p) {
            $propName = $p['name'];
            $type = $p['type'];
            $refProp = new \ReflectionProperty($class, $propName);
            $refProp->setAccessible(true);

            if ($type === Request::class) {
                $refProp->setValue($controller, $req);
            } elseif ($type === Response::class) {
                $refProp->setValue($controller, $res);
            } elseif ($type === self::class) {
                $refProp->setValue($controller, $this);
            } elseif ($this->container && class_exists($type)) {
                $refProp->setValue($controller, $this->container->get($type));
            }
        }

        $args = [];
        foreach ($route['argPlan'] as $arg) {
            switch ($arg['inject']) {
                case 'Request':
                    $args[] = $req;
                    break;
                case 'Response':
                    $args[] = $res;
                    break;
                case 'Route':
                    $args[] = $params[$arg['name']] ?? null;
                    break;
                case 'Query':
                    $args[] = $req->query($arg['name']);
                    break;
                case 'Body':
                    $dto = new $arg['class']($req->body());
                    if ($arg['validate'] ?? false) {
                        $validator = new Validator();
                        $errors = $validator->validate($dto);
                        if ($errors)
                            throw new ValidationException("Validation failed", $errors);
                    }
                    $args[] = $dto;
                    break;
                case 'Scalar':
                    $val = $params[$arg['name']] ?? $req->query($arg['name']) ?? null;
                    settype($val, $arg['type']);
                    $args[] = $val;
                    break;
                default:
                    $args[] = null;
                    break;
            }
        }

        $method = $route['method'];
        $result = $controller->$method(...$args);

        // -------- HTML VIEW RENDERING ---------
        if ($result instanceof View) {
            $res->withHeader('Content-Type', 'text/html');
            $res->write($result->render())->send();
            return;
        }

        // -------- FORMATTER HANDLING ---------
        $formatter = $route['formatter'] ?? ['type' => 'json', 'options' => null];
        if (is_array($formatter) && isset($formatter['type'])) {
            switch ($formatter['type']) {
                case FileFormatter::class:
                case 'file':
                    $options = $formatter['options'] ?? [];
                    $mimetype = $options['mimetype'] ?? 'application/octet-stream';
                    $res->withHeader('Content-Type', $mimetype);

                    if (!empty($options['download'])) {
                        $filename = $options['filename'] ?? (is_string($result) ? basename($result) : 'download.bin');
                        $res->withHeader('Content-Disposition', "attachment; filename=\"$filename\"");
                    }

                    if (is_string($result) && is_file($result)) {
                        $res->write(file_get_contents($result))->send();
                    } else {
                        $res->write(is_scalar($result) ? $result : json_encode($result))->send();
                    }
                    return;

                case SimpleXmlFormatter::class:
                case 'xml':
                    $res->withHeader('Content-Type', 'application/xml');
                    $xml = simplexml_load_string('<root/>');
                    $arrayResult = is_array($result) ? $result : (array) $result;
                    array_walk_recursive($arrayResult, function ($v, $k) use ($xml) {
                        $xml->addChild($k, $v);
                    });
                    $res->write($xml->asXML())->send();
                    return;

                case JSONFormatter::class:
                case 'json':
                    // fall through below
                    break;
            }
        }

        // --- DEFAULT: JSON ---
        $res->withHeader('Content-Type', 'application/json')
            ->write(json_encode($result))
            ->send();
    }

    /**
     * Compile all route plans for the given controllers (run this at cold boot or cache build).
     */
    public static function compileRoutePlans(array $controllers): array
    {
        $staticRoutes = [];
        $dynamicRoutes = [];

        foreach ($controllers as $controller) {
            $rc = new ReflectionClass($controller);

            $prefix = '';
            foreach ($rc->getAttributes(ControllerAttr::class) as $attr) {
                $cp = $attr->newInstance()->getPath();
                $prefix = $cp !== '' ? '/' . trim($cp, '/') : '';
                break;
            }

            foreach ($rc->getMethods() as $method) {
                $routeAttr = null;
                $formatterAttr = null;
                foreach ($method->getAttributes() as $attr) {
                    $instance = $attr->newInstance();
                    if (method_exists($instance, 'getPath') && method_exists($instance, 'getHttpMethod')) {
                        $routeAttr = $instance;
                    }
                    if (
                        $instance instanceof FileFormatter ||
                        $instance instanceof SimpleXmlFormatter ||
                        $instance instanceof JSONFormatter
                    ) {
                        $formatterAttr = $instance;
                    }
                }
                if (!$routeAttr)
                    continue;

                $methodPath = $routeAttr->getPath() ?: '';
                $combined = rtrim($prefix, '/') . '/' . ltrim($methodPath, '/');
                $path = '/' . trim($combined, '/');
                $dynamic = strpos($path, '{') !== false;
                $regex = null;

                $propInject = [];
                foreach ($rc->getProperties() as $prop) {
                    if ($prop->getAttributes(Inject::class)) {
                        $type = $prop->getType()?->getName();
                        $propInject[] = [
                            'name' => $prop->getName(),
                            'type' => $type,
                        ];
                    }
                }

                $argPlan = [];
                foreach ($method->getParameters() as $param) {
                    $type = $param->getType()?->getName();
                    $bodyAttr = $param->getAttributes(Body::class)[0] ?? null;
                    $queryAttr = $param->getAttributes(Query::class)[0] ?? null;
                    $routeAttrP = $param->getAttributes(Param::class)[0] ?? null;

                    if ($type === Request::class) {
                        $argPlan[] = ['inject' => 'Request'];
                    } elseif ($type === Response::class) {
                        $argPlan[] = ['inject' => 'Response'];
                    } elseif ($bodyAttr && $type && class_exists($type)) {
                        $argPlan[] = ['inject' => 'Body', 'class' => $type, 'validate' => true];
                    } elseif ($queryAttr) {
                        $attrInstance = $queryAttr->newInstance();
                        $key = $attrInstance->name ?? $param->getName();
                        $argPlan[] = ['inject' => 'Query', 'name' => $key];
                    } elseif ($routeAttrP) {
                        $attrInstance = $routeAttrP->newInstance();
                        $key = $attrInstance->name ?? $param->getName();
                        $argPlan[] = ['inject' => 'Route', 'name' => $key];
                    } elseif ($type && in_array($type, ['string', 'int', 'float', 'bool'])) {
                        $key = $param->getName();
                        $argPlan[] = ['inject' => 'Scalar', 'name' => $key, 'type' => $type];
                    } else {
                        $argPlan[] = ['inject' => 'Unknown'];
                    }
                }

                if ($dynamic) {
                    $pattern = preg_replace('#\{(\w+)\}#', '(?P<\1>[^/]+)', $path);
                    $regex = '#^' . $pattern . '$#';
                }

                $formatter = $formatterAttr ? [
                    'type' => get_class($formatterAttr),
                    'options' => get_object_vars($formatterAttr),
                ] : ['type' => 'json', 'options' => null];

                $plan = [
                    'httpMethod' => strtoupper($routeAttr->getHttpMethod()),
                    'path' => $path,
                    'regex' => $regex,
                    'controller' => $controller,
                    'method' => $method->getName(),
                    'argPlan' => $argPlan,
                    'propInject' => $propInject,
                    'formatter' => $formatter,
                    'throws' => [],
                ];

                if ($dynamic) {
                    $dynamicRoutes[$plan['httpMethod']][] = $plan;
                } else {
                    $staticRoutes[$plan['httpMethod']][$path] = $plan;
                }
            }
        }
        return ['staticRoutes' => $staticRoutes, 'dynamicRoutes' => $dynamicRoutes];
    }

    /**
     * Get all registered routes (for OpenAPI, etc).
     * @return array
     */
    public function getRoutes(): array
    {
        $routes = [];

        // Static
        foreach ($this->staticRoutes as $method => $byPath) {
            foreach ($byPath as $path => $plan) {
                $routes[] = (object) [
                    'method' => $method,
                    'rawPath' => $path,
                    'handlerSpec' => [$plan['controller'], $plan['method']],
                    // You may add more as needed (formatter, etc)
                ];
            }
        }

        // Dynamic
        foreach ($this->dynamicRoutes as $method => $plans) {
            foreach ($plans as $plan) {
                $routes[] = (object) [
                    'method' => $method,
                    'rawPath' => $plan['path'],
                    'handlerSpec' => [$plan['controller'], $plan['method']],
                    // You may add more as needed (formatter, regex, etc)
                ];
            }
        }

        return $routes;
    }
}
