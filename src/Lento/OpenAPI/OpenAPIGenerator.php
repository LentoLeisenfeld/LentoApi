<?php

namespace Lento\OpenAPI;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

use Lento\OpenAPI;
use Lento\OpenAPI\Attributes\{Ignore, Property};
use Lento\Routing\Router;
use Lento\Routing\Attributes\Param;

/**
 * OpenAPI Generator (patched to include scalar/simple endpoints)
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
    }

    public function generate(): array
    {
        $options = OpenAPI::getOptions();

        return array_filter([
            'openapi' => '3.0.0',
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
        // PATCH: handle stdClass with handlerSpec as array
        $handlerSpec = null;
        if (is_object($route) && is_array($route->handlerSpec) && isset($route->handlerSpec['spec'])) {
            $handlerSpec = $route->handlerSpec['spec'];
        } elseif (is_array($route) && isset($route['handlerSpec']['spec'])) {
            $handlerSpec = $route['handlerSpec']['spec'];
        }
        if (!is_array($handlerSpec) || count($handlerSpec) !== 2) continue;
        [$controllerClass, $methodName] = $handlerSpec;

        $methodRef = is_object($route) ? ($route->method ?? null) : ($route['method'] ?? null);
        $rawPath = is_object($route) ? ($route->rawPath ?? null) : ($route['rawPath'] ?? null);
        if (!$methodRef || !$rawPath) continue;

        if (!$controllerClass || !class_exists($controllerClass)) {
            error_log("OpenAPIGenerator: Controller class '$controllerClass' does not exist (route '$rawPath'). Skipping.");
            continue;
        }

        $refClass = new \ReflectionClass($controllerClass);
        if (!$refClass->hasMethod($methodName)) continue;
        $refMethod = $refClass->getMethod($methodName);

        if ($this->isIgnored($refClass, $refMethod)) continue;

        $httpMethod = strtolower($methodRef);
        $operation = $this->buildOperation($refMethod);

        $paths[$rawPath][$httpMethod] = $operation;
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

        // Patch: always include empty arrays for 'parameters' and 'responses'
        $operation = array_filter([
            'summary' => ucfirst($method->getName()),
            'operationId' => $method->getDeclaringClass()->getShortName() . '_' . $method->getName(),
            'tags' => [$method->getDeclaringClass()->getShortName()],
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
                    error_log("OpenAPIGenerator: Parameter type '$typeName' does not exist (method {$method->getName()}). Skipping.");
                    continue;
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
                // Patch: correct OpenAPI type for scalar return
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
            error_log("OpenAPIGenerator: generateModelSchema - class '$fqcn' does not exist.");
            return ['type' => 'object', 'properties' => []];
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
                error_log("OpenAPIGenerator: Property '$name' in class '{$rc->getName()}' has no type. Skipping.");
                continue;
            }

            if ($type->isBuiltin()) {
                $schema['properties'][$name] = ['type' => $this->mapType($typeName)];
            } else {
                if (!class_exists($typeName)) {
                    error_log("OpenAPIGenerator: Property type '$typeName' does not exist (property '$name' in class '{$rc->getName()}'). Skipping.");
                    continue;
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
