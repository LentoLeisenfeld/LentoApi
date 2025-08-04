<?php

namespace Lento\OpenAPI;

use Lento\Routing\Router;
use Lento\Cache;
use Lento\Logging\Logger;

/**
 * OpenAPI Generator (cache-driven, zero-reflection hot path)
 */
class OpenAPIGenerator
{
    private Router $router;
    private array $attributeCache;
    private array $processedModels = [];
    private array $components = [
        'schemas' => [],
        'securitySchemes' => [],
    ];

    public function __construct(Router $router)
    {
        $this->router = $router;
        $this->attributeCache = Cache::loadAttributes();

        if (empty($this->components['securitySchemes'])) {
            $this->components['securitySchemes'] = new \stdClass();
        }
    }

    public function generate(): array
    {
        $options = \Lento\OpenAPI::getOptions();

        return array_filter([
            'openapi' => '3.1.0',
            'info' => \Lento\OpenAPI::getInfo(),
            'paths' => $this->buildPaths(),
            'components' => $this->components,
            'security' => $options->security ?: null,
            'tags' => $options->tags ?: null,
            'externalDocs' => $options->externalDocs ?: null,
        ]);
    }

    protected function buildPaths(): array
    {
        $paths = [];

        foreach ($this->router->getRoutes() as $route) {
            $handlerSpec = $this->getHandlerSpec($route);
            if (!$handlerSpec) {
                continue;
            }

            [$controllerClass, $methodName] = $handlerSpec;

            $methodRef = is_object($route) ? ($route->method ?? null) : ($route['method'] ?? null);
            $rawPath = is_object($route) ? ($route->rawPath ?? null) : ($route['rawPath'] ?? null);
            if (!$methodRef || !$rawPath) {
                continue;
            }

            // Use attribute cache instead of reflection
            $classAttrs = $this->attributeCache[$controllerClass]['__class'] ?? [];
            $methodAttrs = $this->attributeCache[$controllerClass]['methods'][$methodName]['__method'] ?? [];

            if ($this->isIgnored($classAttrs, $methodAttrs)) {
                continue;
            }

            $httpMethod = strtolower($methodRef);

            $operation = $this->buildOperation($controllerClass, $methodName, $methodAttrs, $classAttrs);

            $paths[$rawPath][$httpMethod] = $operation;
        }

        ksort($paths);

        $httpOrder = ['get', 'post', 'put', 'patch', 'delete'];
        foreach ($paths as &$methods) {
            uksort($methods, function ($a, $b) use ($httpOrder) {
                $posA = array_search($a, $httpOrder);
                $posB = array_search($b, $httpOrder);
                $posA = $posA === false ? PHP_INT_MAX : $posA;
                $posB = $posB === false ? PHP_INT_MAX : $posB;
                return $posA <=> $posB;
            });
        }
        return $paths;
    }

    protected function getHandlerSpec($route): ?array
    {
        // Handles both object and array representations
        if (is_object($route) && is_array($route->handlerSpec)) {
            if (isset($route->handlerSpec[0], $route->handlerSpec[1])) {
                return $route->handlerSpec;
            } elseif (isset($route->handlerSpec['spec'])) {
                return $route->handlerSpec['spec'];
            }
        } elseif (is_array($route) && isset($route['handlerSpec']['spec'])) {
            return $route['handlerSpec']['spec'];
        }
        return null;
    }

    protected function isIgnored(array $classAttrs, array $methodAttrs): bool
    {
        foreach (array_merge($classAttrs, $methodAttrs) as $attr) {
            if (($attr['name'] ?? null) === \Lento\OpenAPI\Attributes\Ignore::class) {
                Logger::info("ignored");
                return true;
            }
        }
        return false;
    }

    protected function buildOperation(string $controllerClass, string $methodName, array $methodAttrs, array $classAttrs): array
    {
        // Parameters and requestBody
        [$parameters, $requestBody, $schemas] = $this->extractParameters($controllerClass, $methodName);

        foreach ($schemas as $name => $fqcn) {
            if (!isset($this->processedModels[$name]) && class_exists($fqcn)) {
                $this->components['schemas'][$name] = $this->generateModelSchema($fqcn);
                $this->processedModels[$name] = true;
            }
        }

        $responses = $this->getResponseSchemas($controllerClass, $methodName);

        // Throws: error responses
        foreach ($methodAttrs as $attr) {
            if (($attr['name'] ?? null) === \Lento\OpenAPI\Attributes\Throws::class) {
                $throws = $attr['args'] ?? [];
                $statusCode = isset($throws['status']) ? (string) $throws['status'] : '500';
                $desc = $throws['description'] ?? $throws['exception'] ?? "Error";
                if (!isset($responses[$statusCode])) {
                    $responses[$statusCode] = [
                        'description' => $desc,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'error' => ['type' => 'string'],
                                    ],
                                    'required' => ['error'],
                                ],
                            ],
                        ],
                    ];
                }
            }
        }

        // Summary
        $summary = $this->extractSummary($methodAttrs, $controllerClass, $methodName);

        // Tags (from method/class)
        $tags = array_merge(
            $this->extractTags($classAttrs),
            $this->extractTags($methodAttrs)
        );
        $tags = array_values(array_unique($tags));

        $deprecated = $this->isDeprecated($methodAttrs);

        $operation = array_filter([
            'summary' => $summary,
            'operationId' => $controllerClass . '_' . $methodName,
            'tags' => $tags,
            'parameters' => $parameters ?: [],
            'requestBody' => $requestBody,
            'responses' => $responses ?: [],
            'deprecated' => $deprecated,
            'security' => null,
            'externalDocs' => null,
        ], function ($v) {
            return $v !== null;
        });

        return $operation;
    }

    protected function extractParameters(string $controllerClass, string $methodName): array
    {
        $params = [];
        $requestBody = null;
        $schemas = [];

        $paramsAttrs = $this->attributeCache[$controllerClass]['methods'][$methodName]['parameters'] ?? [];

        foreach ($paramsAttrs as $paramName => $paramAttrs) {
            $type = null;
            $hasParamAttr = false;
            foreach ($paramAttrs as $attr) {
                if ($attr['name'] === \Lento\Routing\Attributes\Param::class) {
                    $hasParamAttr = true;
                }
                // Try to extract type from attribute args (if provided by your cache builder)
                if (isset($attr['args']['type'])) {
                    $type = $attr['args']['type'];
                }
            }

            // Fallback type detection for demo (may need expansion)
            $type = $type ?? 'string';

            if ($hasParamAttr) {
                $params[] = [
                    'name' => $paramName,
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => $this->mapType($type)],
                ];
            } else {
                // For requestBody: treat non-builtin as a DTO schema
                if (!in_array($type, ['string', 'int', 'integer', 'float', 'bool', 'boolean', 'array', 'number'])) {
                    $short = (new \ReflectionClass($type))->getShortName();
                    $schemas[$short] = $type;
                    $requestBody = [
                        'required' => true,
                        'content' => [
                            'application/json' => ['schema' => ['$ref' => "#/components/schemas/$short"]]
                        ],
                    ];
                }
            }
        }

        return [$params, $requestBody, $schemas];
    }

    protected function getResponseSchemas(string $controllerClass, string $methodName): array
    {
        $responses = [];
        // Could add return type info to attributes cache at build-time for even less reflection
        $schema = ['type' => 'object'];
        $responses['200'] = [
            'description' => 'Successful response',
            'content' => ['application/json' => ['schema' => $schema]],
        ];
        return $responses;
    }

    protected function generateModelSchema(string $fqcn): array
    {
        if (!$fqcn || !class_exists($fqcn)) {
            throw new \RuntimeException("OpenAPIGenerator: Class $fqcn does not exist");
        }

        $rc = new \ReflectionClass($fqcn);
        $schema = ['type' => 'object', 'properties' => [], 'required' => []];

        foreach ($rc->getProperties() as $prop) {
            if (!$prop->isPublic())
                continue;
            $name = $prop->getName();
            $type = $prop->getType();
            $typeName = $type ? $type->getName() : 'string';

            if (in_array($typeName, ['string', 'int', 'integer', 'float', 'bool', 'boolean', 'array', 'number'])) {
                $schema['properties'][$name] = ['type' => $this->mapType($typeName)];
            } else {
                $short = (new \ReflectionClass($typeName))->getShortName();
                $schema['properties'][$name] = ['$ref' => "#/components/schemas/$short"];
                if (!isset($this->processedModels[$short])) {
                    $this->components['schemas'][$short] = $this->generateModelSchema($typeName);
                    $this->processedModels[$short] = true;
                }
            }
            $schema['required'][] = $name;
        }
        return $schema;
    }

    protected function mapType(string $phpType): string
    {
        return match ($phpType) {
            'int', 'integer' => 'integer',
            'float', 'double', 'number' => 'number',
            'bool', 'boolean' => 'boolean',
            default => 'string',
        };
    }

    protected function extractTags(array $attrs): array
    {
        foreach ($attrs as $attr) {
            if (($attr['name'] ?? null) === \Lento\OpenAPI\Attributes\Tags::class) {
                // Positional
                if (isset($attr['args'][0])) {
                    return (array) $attr['args'][0];
                }
                // Named
                if (isset($attr['args']['tags'])) {
                    return (array) $attr['args']['tags'];
                }
            }
        }
        return [];
    }

    protected function extractSummary(array $methodAttrs, string $controllerClass, string $methodName): string
    {
        foreach ($methodAttrs as $attr) {
            if (($attr['name'] ?? null) === \Lento\OpenAPI\Attributes\Summary::class) {
                // Positional
                if (isset($attr['args'][0]))
                    return $attr['args'][0];
                // Named
                if (isset($attr['args']['text']))
                    return $attr['args']['text'];
            }
        }
        // Default fallback
        return "$controllerClass->$methodName";
    }

    protected function isDeprecated(array $methodAttrs): bool
    {
        foreach ($methodAttrs as $attr) {
            if (($attr['name'] ?? null) === \Deprecated::class) {
                return true;
            }
        }
        return false;
    }
}
