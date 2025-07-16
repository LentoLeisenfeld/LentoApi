<?php

namespace Lento\OpenAPI;

use Lento\Enums\Message;
use Lento\OpenAPI\Attributes\{Summary, Tags};
use Lento\OpenAPI\Attributes\Deprecated;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Lento\OpenAPI;
use Lento\OpenAPI\Attributes\Ignore;
use Lento\OpenAPI\Attributes\Property;
use Lento\Routing\Router;
use Lento\Routing\Attributes\Param;
use Lento\OpenAPI\Attributes\Throws;
use Lento\Logging\Logger;

/**
 * OpenAPI Generator (exception safe + Throws integration)
 */
class OpenAPIGenerator
{
    /**
     * @var Router
     */
    private Router $router;

    /**
     * @var array<string,bool>
     */
    private array $processedModels = [];

    /**
     * @var array
     */
    private array $components = [
        'schemas' => [],
        'securitySchemes' => [],
    ];

    public function __construct(Router $router)
    {
        $this->router = $router;

        // Ensure securitySchemes is an object for OpenAPI
        if (empty($this->components['securitySchemes'])) {
            $this->components['securitySchemes'] = new \stdClass();
        }
    }

    public function generate(): array
    {
        $options = OpenAPI::getOptions();

        return array_filter([
            'openapi' => '3.1.0',
            'info' => OpenAPI::getInfo(),
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
            $handlerSpec = null;

            if (is_object($route) && is_array($route->handlerSpec)) {
                if (isset($route->handlerSpec[0], $route->handlerSpec[1])) {
                    $handlerSpec = $route->handlerSpec;
                } elseif (isset($route->handlerSpec['spec'])) {
                    $handlerSpec = $route->handlerSpec['spec'];
                }
            } elseif (is_array($route) && isset($route['handlerSpec']['spec'])) {
                $handlerSpec = $route['handlerSpec']['spec'];
            }

            if (!is_array($handlerSpec) || count($handlerSpec) !== 2) {
                Logger::debug(message: "OpenAPIGenerator: Skipping route due to invalid handlerSpec");
                continue;
            }

            [$controllerClass, $methodName] = $handlerSpec;

            $methodRef = is_object($route) ? ($route->method ?? null) : ($route['method'] ?? null);
            $rawPath = is_object($route) ? ($route->rawPath ?? null) : ($route['rawPath'] ?? null);
            if (!$methodRef || !$rawPath) {
                continue;
            }

            if (!class_exists($controllerClass)) {
                throw new RuntimeException("OpenAPIGenerator: Controller class '$controllerClass' does not exist for route '$rawPath'.");
            }

            $refClass = new ReflectionClass($controllerClass);
            if (!$refClass->hasMethod($methodName)) {
                throw new RuntimeException("OpenAPIGenerator: Method '$methodName' not found in class '$controllerClass'.");
            }

            $refMethod = $refClass->getMethod($methodName);

            if ($this->isIgnored($refClass, $refMethod)) {
                continue;
            }

            $httpMethod = strtolower($methodRef);
            $operation = $this->buildOperation($refMethod);

            $paths[$rawPath][$httpMethod] = $operation;
        }
        ksort(array: $paths);

        $httpOrder = ['get', 'post', 'put', 'patch', 'delete'];

        foreach ($paths as &$methods) {
            uksort($methods, function ($a, $b) use ($httpOrder) {
                $posA = array_search($a, $httpOrder);
                $posB = array_search($b, $httpOrder);

                // Unknown methods go last
                $posA = $posA === false ? PHP_INT_MAX : $posA;
                $posB = $posB === false ? PHP_INT_MAX : $posB;

                return $posA <=> $posB;
            });
        }

        return $paths;
    }


    protected function isIgnored(ReflectionClass $refClass, ReflectionMethod $refMethod): bool
    {
        return (bool) $refClass->getAttributes(Ignore::class)
            || (bool) $refMethod->getAttributes(Ignore::class);
    }

    protected function buildOperation(ReflectionMethod $method): array
    {
        [$parameters, $requestBody, $schemas] = $this->extractParameters($method);

        foreach ($schemas as $name => $fqcn) {
            if (!isset($this->processedModels[$name]) && class_exists($fqcn)) {
                $this->components['schemas'][$name] = $this->generateModelSchema($fqcn);
                $this->processedModels[$name] = true;
            }
        }

        $responses = $this->getResponseSchemas($method);

        // --- NEW: add Throws attribute responses ---
        $throwsAttrs = $method->getAttributes(Throws::class, \ReflectionAttribute::IS_INSTANCEOF);
        foreach ($throwsAttrs as $attr) {
            $throws = $attr->newInstance();
            $statusCode = (string) $throws->status;
            $desc = $throws->description ?? $throws->exception ?? "Error";
            if (!isset($responses[$statusCode])) {
                $responses[$statusCode] = [
                    'description' => $desc,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'error' => ['type' => 'string'],
                                    // Optionally add 'details'
                                ],
                                'required' => ['error'],
                            ],
                        ],
                    ],
                ];
            }
        }

        $summaryAttribute = $method->getAttributes(Summary::class, \ReflectionAttribute::IS_INSTANCEOF)[0]?->newInstance();

        $tagsClassAttributes = $method->getDeclaringClass()->getAttributes(Tags::class, \ReflectionAttribute::IS_INSTANCEOF);
        $tagsMethodAttributes = $method->getAttributes(Tags::class, \ReflectionAttribute::IS_INSTANCEOF);

        $tagsClassAttribute = $tagsClassAttributes[0] ?? null;
        $tagsMethodAttribute = $tagsMethodAttributes[0] ?? null;

        $tagsClass = $tagsClassAttribute?->newInstance();
        $tagsMethod = $tagsMethodAttribute?->newInstance();
        $tags = [];

        if ($tagsClass instanceof Tags) {
            $tags = array_merge($tags, $tagsClass->tags);
        }

        if ($tagsMethod instanceof Tags) {
            $tags = array_merge($tags, $tagsMethod->tags);
        }

        $distinctTags = array_values(array_unique($tags));

        $operation = array_filter([
            'summary' => $summaryAttribute?->text ?? $method->getDeclaringClass()->getName() . '->' . $method->getName(),
            'operationId' => $method->getDeclaringClass()->getShortName() . '_' . $method->getName(),
            'tags' => $distinctTags,
            'parameters' => $parameters ?: [],
            'requestBody' => $requestBody,
            'responses' => $responses ?: [],
            'deprecated' => $method->isDeprecated() ?: null,
            'security' => $this->getSecurity($method),
            'externalDocs' => $this->getExternalDocs($method),
        ], function ($v) {
            return $v !== null;
        });

        return $operation;
    }

    /**
     * Extract path parameters and request body schemas.
     *
     * @return array{array,array|null,array}
     */
    protected function extractParameters(ReflectionMethod $method): array
    {
        $params = [];
        $requestBody = null;
        $schemas = [];

        foreach ($method->getParameters() as $param) {
            $hasParamAttr = (bool) $param->getAttributes(Param::class);
            $type = $param->getType();
            if (!$type instanceof ReflectionNamedType) {
                continue;
            }
            $typeName = $type->getName();

            if (!$typeName) {
                continue;
            }

            if (!$type->isBuiltin()) {
                if (!class_exists($typeName)) {
                    throw new RuntimeException(
                        Message::GeneratorPropertyDoesNotExist->interpolate(name: $typeName, method: $method->getName())
                    );
                }
                $short = (new ReflectionClass($typeName))->getShortName();
                $schemas[$short] = $typeName;
                $requestBody = [
                    'required' => true,
                    'content' => [
                        'application/json' => ['schema' => ['$ref' => "#/components/schemas/$short"]]
                    ],
                ];
            } elseif ($hasParamAttr) {
                $params[] = [
                    'name' => $param->getName(),
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => $this->mapType($typeName)],
                ];
            }
        }

        return [$params, $requestBody, $schemas];
    }

    /**
     * Get response schemas for method return type.
     *
     * @param ReflectionMethod $method
     * @return array
     */
    protected function getResponseSchemas(ReflectionMethod $method): array
    {
        $responses = [];
        $returnType = $method->getReturnType();

        if ($returnType instanceof ReflectionNamedType) {
            $typeName = $returnType->getName();

            if (!$returnType->isBuiltin()) {
                if ($typeName && class_exists($typeName)) {
                    $short = (new ReflectionClass($typeName))->getShortName();
                    if (!isset($this->processedModels[$short])) {
                        $this->components['schemas'][$short] = $this->generateModelSchema($typeName);
                        $this->processedModels[$short] = true;
                    }
                    $schema = ['$ref' => "#/components/schemas/$short"];
                } else {
                    $schema = ['type' => 'object'];
                }
            } else {
                $schema = ['type' => $this->mapType($typeName)];
            }
        } else {
            $schema = ['type' => 'object'];
        }

        $responses['200'] = [
            'description' => 'Successful response',
            'content' => ['application/json' => ['schema' => $schema]],
        ];

        return $responses;
    }

    protected function generateModelSchema(string $fqcn): array
    {
        if (!$fqcn || !class_exists($fqcn)) {
            throw new RuntimeException(
                Message::GeneratorClassDoesNotExist->interpolate(fqcn: $fqcn)
            );
        }

        $rc = new ReflectionClass($fqcn);
        $schema = ['type' => 'object', 'properties' => [], 'required' => []];

        foreach ($rc->getProperties() as $prop) {
            if (!$prop->isPublic() || !$prop->getAttributes(Property::class)) {
                continue;
            }
            $name = $prop->getName();
            $type = $prop->getType();
            if (!$type instanceof ReflectionNamedType) {
                continue;
            }
            $typeName = $type->getName();

            if (!$typeName) {
                throw new RuntimeException(
                    Message::GeneratorPropertyHasNoType->interpolate(
                        name: $name,
                        rc: $rc->getName()
                    )
                );
            }

            if ($type->isBuiltin()) {
                $schema['properties'][$name] = ['type' => $this->mapType($typeName)];
            } else {
                if (!class_exists($typeName)) {
                    throw new RuntimeException(
                        Message::GeneratorPropertyDoesNotExist->interpolate(
                            type: $typeName,
                            name: $name,
                            rc: $rc->getName()
                        )
                    );
                }
                $short = (new ReflectionClass($typeName))->getShortName();
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
            'int' => 'integer',
            'float', 'double' => 'number',
            'bool' => 'boolean',
            default => 'string',
        };
    }

    protected function getSecurity(ReflectionMethod $method): ?array
    {
        return null;
    }

    protected function getExternalDocs(ReflectionMethod $method): ?array
    {
        return null;
    }
}
