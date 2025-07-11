<?php

/**
 * Lento API Router
 *
 * High-performance HTTP router with attribute-based controller discovery,
 * dependency injection, dynamic/static route handling, precompiled closures,
 * formatter attributes (JSON/XML/File/Static), and automated error responses.
 *
 * @package Lento\Routing
 * @author  Lento Leisenfeld
 * @license MIT
 */

namespace Lento\Routing;

use ReflectionClass;
use DomainException;
use LogicException;
use Throwable;

use Lento\Formatter\Attributes\{JSONFormatter, SimpleXmlFormatter, FileFormatter};
use Lento\Container;
use Lento\Http\{Request, Response};
use Lento\Routing\Attributes\{Service, Body, Param, Query, Inject};
use Lento\Auth\JWT;
use Lento\Exceptions\{NotFoundException, UnauthorizedException, ForbiddenException, ValidationException};

/**
 * Class Router
 *
 * @package Lento\Routing
 * @psalm-type HandlerSpec=array{0:string,1:string}
 */
#[Service]
class Router
{
    /** @var array<string, array<string, array>> */
    private array $staticRoutes = [];
    /** @var array<string, array<array>> */
    private array $dynamicRoutes = [];
    /** @var array<string, array<string, HandlerSpec>> */
    private array $staticSpecs = [];
    /** @var array<string, array<array{path: string, spec: HandlerSpec}>> */
    private array $dynamicSpecs = [];

    private ?Container $container = null;

    /**
     * Set the DI container for controller/service injection.
     */
    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    /**
     * Add a new route and compile its handler closure.
     *
     * @param string      $method       HTTP method (GET/POST/...)
     * @param string      $path         Route path (can contain {placeholders})
     * @param HandlerSpec $handlerSpec  [ControllerClass, methodName]
     * @param mixed|null  $formatterAttr Optional formatter info/attribute
     */
    public function addRoute(string $method, string $path, array $handlerSpec, $formatterAttr = null): void
    {
        $normalized = '/' . ltrim(rtrim($path, '/'), '/');
        $m = strtoupper($method);

        // Use formatter attribute if given, else default to JSON
        $formatter = $formatterAttr ?: ['type' => 'json', 'options' => null];

        $routeData = [
            'handler' => $this->makeHandler($handlerSpec),
            'formatter' => $formatter,
            'spec' => $handlerSpec,
        ];

        // Patch: Save formatter in staticSpecs and dynamicSpecs for cache
        $specEntry = [
            'spec' => $handlerSpec,
            'formatter' => self::exportFormatter($formatter),
        ];

        if (strpos($normalized, '{') === false) {
            $this->staticRoutes[$m][$normalized] = $routeData;
            $this->staticSpecs[$m][$normalized] = $specEntry;
        } else {
            $pattern = preg_replace('#\{(\w+)\}#', '(?P<\1>[^/]+)', $normalized);
            $regex = '#^' . $pattern . '$#';
            $this->dynamicRoutes[$m][] = [$regex, $routeData];
            $this->dynamicSpecs[$m][] = [
                'path' => $normalized,
                'spec' => $handlerSpec,
                'formatter' => self::exportFormatter($formatter)
            ];
        }
    }

    /**
     * Helper: resolve formatter attribute, return ['type'=>..., 'options'=>...]
     */
    private static function resolveFormatterAttr(array $attrs): ?array
    {
        foreach ($attrs as $attr) {
            $n = $attr->getName();
            switch ($n) {
                case SimpleXmlFormatter::class:
                    return ['type' => 'xml', 'options' => null];
                case FileFormatter::class:
                    return ['type' => 'file', 'options' => $attr->newInstance()];
                case JSONFormatter::class:
                    return ['type' => 'json', 'options' => null];
            }
        }
        return null;
    }

    /**
     * Dispatch the incoming request to the matched route.
     *
     * @return mixed|null
     */
    public function dispatch(string $uri, string $httpMethod, Request $req, Response $res)
    {
        $jwtPayload = JWT::fromRequestHeaders($req->headers);
        $req->jwt = $jwtPayload;

        $path = '/' . ltrim(rtrim($uri, '/'), '/');
        $m = strtoupper($httpMethod);

        $routeData = $this->staticRoutes[$m][$path] ?? null;
        $params = [];

        if (!$routeData) {
            foreach ($this->dynamicRoutes[$m] ?? [] as [$regex, $routeCandidate]) {
                if (preg_match($regex, $path, $matches)) {
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    $routeData = $routeCandidate;
                    break;
                }
            }
        }

        if (!$routeData) {
            return $res->status(404)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'Not found']))->send();
        }

        $handler = $routeData['handler'];
        $formatter = $routeData['formatter'];

        try {
            $result = $handler($params, $req, $res);
        } catch (NotFoundException $e) {
            return $res->status(404)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => $e->getMessage()]))->send();
        } catch (UnauthorizedException $e) {
            return $res->status(401)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => $e->getMessage()]))->send();
        } catch (ForbiddenException $e) {
            return $res->status(403)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => $e->getMessage()]))->send();
        } catch (ValidationException $e) {
            $body = ['error' => $e->getMessage()];
            if (method_exists($e, 'getErrors')) {
                $body['details'] = $e->getErrors();
            }
            return $res->status(422)->withHeader('Content-Type', 'application/json')
                ->write(json_encode($body))->send();
        } catch (DomainException | LogicException $e) {
            return $res->status(400)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => $e->getMessage()]))->send();
        } catch (Throwable $e) {
            return $res->status(500)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(['error' => 'Internal Server Error']))->send();
        }

        // -------- FORMATTER HANDLING ---------
        // Attribute-based (class instance) formatters
        if (is_object($formatter)) {
            switch (get_class($formatter)) {
                case FileFormatter::class:
                    $mimetype = $formatter->mimetype ?? 'application/octet-stream';
                    $res->withHeader('Content-Type', $mimetype);

                    if ($formatter->download) {
                        $filename = $formatter->filename ?? (is_string($result) ? basename($result) : 'download.bin');
                        $res->withHeader('Content-Disposition', "attachment; filename=\"$filename\"");
                    }

                    if (is_string($result) && is_file($result)) {
                        $res->write(file_get_contents($result))->send();
                    } else {
                        $res->write(is_scalar($result) ? $result : json_encode($result))->send();
                    }
                    return;

                case SimpleXmlFormatter::class:
                    $res->withHeader('Content-Type', 'application/xml');
                    $xml = simplexml_load_string('<root/>');
                    $arrayResult = is_array($result) ? $result : (array) $result;
                    array_walk_recursive($arrayResult, function ($v, $k) use ($xml) {
                        $xml->addChild($k, $v);
                    });
                    $res->write($xml->asXML())->send();
                    return;

                case JSONFormatter::class:
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
     * Export route specs for cache.
     *
     * @return array
     */
    public function exportCacheData(): array
    {
        return [
            'staticSpecs' => $this->staticSpecs,
            'dynamicSpecs' => $this->dynamicSpecs,
        ];
    }

    /**
     * Import (precompiled) route specs from cache.
     */
    public function importCacheData(array $data): void
    {
        // Static routes
        foreach ($data['staticSpecs'] ?? [] as $method => $routes) {
            foreach ($routes as $path => $entry) {
                $spec = $entry['spec'];
                $formatterAttr = $entry['formatter'] ?? ['type' => 'json', 'options' => null];
                $routeData = [
                    'handler' => $this->makeHandler($spec),
                    'formatter' => self::importFormatter($formatterAttr),
                    'spec' => $spec,
                ];

                $this->staticSpecs[$method][$path] = $entry;
                $this->staticRoutes[$method][$path] = $routeData;
            }
        }

        // Dynamic routes
        foreach ($data['dynamicSpecs'] ?? [] as $method => $entries) {
            foreach ($entries as $entry) {
                $spec = $entry['spec'];
                $formatterAttr = $entry['formatter'] ?? ['type' => 'json', 'options' => null];
                $pattern = preg_replace('#\{(\w+)\}#', '(?P<\1>[^/]+)', $entry['path']);
                $regex = '#^' . $pattern . '$#';

                $routeData = [
                    'handler' => $this->makeHandler($spec),
                    'formatter' => self::importFormatter($formatterAttr),
                    'spec' => $spec,
                ];

                $this->dynamicSpecs[$method][] = $entry;
                $this->dynamicRoutes[$method][] = [$regex, $routeData];
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param [type] $data
     * @return array|FileFormatter
     */
    private static function importFormatter($data): array|FileFormatter
    {
        if (is_array($data) && isset($data['type'])) {
            switch ($data['type']) {
                case FileFormatter::class:
                    return new FileFormatter(
                        $data['options']['mimetype'] ?? null,
                        $data['options']['filename'] ?? null,
                        $data['options']['download'] ?? false
                    );
                case SimpleXmlFormatter::class:
                case 'xml':
                    return ['type' => 'xml', 'options' => null];
                case JSONFormatter::class:
                case 'json':
                    return ['type' => 'json', 'options' => null];
            }
        }
        return ['type' => 'json', 'options' => null];
    }

    /**
     * Undocumented function
     *
     * @param [type] $formatter
     * @return array
     */
    private static function exportFormatter($formatter): array
    {
        if (is_array($formatter)) {
            // Old format (type/options), e.g. ['type' => 'json', ...]
            if (isset($formatter['options']) && is_object($formatter['options'])) {
                return [
                    'type' => $formatter['type'],
                    'options' => get_object_vars($formatter['options']),
                ];
            }
            return $formatter;
        }
        if (is_object($formatter)) {
            return [
                'type' => get_class($formatter),
                'options' => get_object_vars($formatter),
            ];
        }
        return ['type' => 'json', 'options' => null];
    }

    /**
     * Precompiled handler generator using the DI container.
     *
     * @param HandlerSpec $handlerSpec
     * @return callable
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
            $rc = new ReflectionClass($controller);
            foreach ($rc->getProperties() as $prop) {
                if ($prop->getAttributes(Inject::class)) {
                    $type = $prop->getType()?->getName();
                    $prop->setAccessible(true);
                    if ($type === Request::class) {
                        $prop->setValue($controller, $req);
                    } elseif ($type === Response::class) {
                        $prop->setValue($controller, $res);
                    } elseif ($type === self::class) {
                        $prop->setValue($controller, $this);
                    } elseif ($this->container && $type && class_exists($type)) {
                        try {
                            $service = $this->container->get($type);
                            $prop->setValue($controller, $service);
                        } catch (Throwable $e) {
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

                if ($t === Request::class) {
                    $args[] = $req;
                } elseif ($t === Response::class) {
                    $args[] = $res;
                } else {
                    // Check all parameter attributes
                    $paramAttr =
                        $param->getAttributes(Param::class)[0] ??
                        $param->getAttributes(Route::class)[0] ??
                        $param->getAttributes(Query::class)[0] ??
                        $param->getAttributes(Body::class)[0] ?? null;

                    // Determine source and name
                    if ($paramAttr) {
                        $attrName = $paramAttr->getName();
                        $attrInstance = $paramAttr->newInstance();
                        $key = $attrInstance->name ?? $param->getName();

                        // Map class name to source
                        $source = match ($attrName) {
                            Param::class => $attrInstance->source ?? 'route',
                            Route::class => 'route',
                            Query::class => 'query',
                            Body::class => 'body',
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
     * Get all registered routes (for documentation or OpenAPI).
     *
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
