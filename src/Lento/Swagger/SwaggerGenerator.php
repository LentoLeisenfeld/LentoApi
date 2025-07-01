<?php

namespace Lento\Swagger;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Lento\Attributes\{Ignore, Property, Param, Deprecated};
use Lento\Routing\Router;
use Lento\Http\Request;
use Lento\Http\Response;
use Lento\Swagger;

class SwaggerGenerator
{
    private Router $router;
    private array $processedModels = [];
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
        $options = Swagger::getOptions();

        return array_filter([
            'openapi' => '3.0.0',
            'info' => Swagger::getInfo(),
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

        // Use router's public method to fetch all Route objects
        foreach ($this->router->getRoutes() as $route) {
            $spec = $route->handlerSpec;
            if (!is_array($spec) || count($spec) !== 2) {
                continue;
            }
            [$controllerClass, $methodName] = $spec;

            $refClass = new ReflectionClass($controllerClass);
            $refMethod = $refClass->getMethod($methodName);

            if ($this->isIgnored($refClass, $refMethod)) {
                continue;
            }

            $httpMethod = strtolower($route->method);
            $path = $route->rawPath;

            $operation = $this->buildOperation($refMethod, $route);

            $paths[$path][$httpMethod] = $operation;
        }

        return $paths;
    }

    protected function isIgnored(ReflectionClass $refClass, ReflectionMethod $refMethod): bool
    {
        return $refClass->getAttributes(Ignore::class)
            || $refMethod->getAttributes(Ignore::class);
    }

    protected function buildOperation(ReflectionMethod $method, $route): array
    {
        [$parameters, $requestBody, $schemas] = $this->extractParameters($method);

        foreach ($schemas as $name => $fqcn) {
            if (!isset($this->processedModels[$name])) {
                $this->components['schemas'][$name] = $this->generateModelSchema($fqcn);
                $this->processedModels[$name] = true;
            }
        }

        $responses = $this->getResponseSchemas($method);

        $operation = array_filter([
            'summary' => ucfirst($method->getName()),
            'operationId' => $method->getDeclaringClass()->getShortName() . '_' . $method->getName(),
            'tags' => [$method->getDeclaringClass()->getShortName()],
            'parameters' => $parameters,
            'requestBody' => $requestBody,
            'responses' => $responses,
            'deprecated' => $method->getAttributes(Deprecated::class) ? true : false,
            'security' => $this->getSecurity($method),
            'externalDocs' => $this->getExternalDocs($method),
        ]);

        return $operation;
    }

    protected function extractParameters(ReflectionMethod $method): array
    {
        $params = [];
        $requestBody = null;
        $schemas = [];

        foreach ($method->getParameters() as $param) {
            $attrs = $param->getAttributes(Param::class);
            $type = $param->getType();
            if (!$type instanceof ReflectionNamedType) {
                continue;
            }
            $typeName = $type->getName();

            if (!$type->isBuiltin()) {
                // Complex type -> requestBody
                $short = (new ReflectionClass($typeName))->getShortName();
                $schemas[$short] = $typeName;
                $requestBody = [
                    'required' => true,
                    'content' => [
                        'application/json' => ['schema' => ['$ref' => "#/components/schemas/$short"]]
                    ],
                ];
            } elseif ($attrs) {
                // Path parameter
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

    protected function getResponseSchemas(ReflectionMethod $method): array
    {
        $responses = [];
        $returnType = $method->getReturnType();
        if ($returnType instanceof ReflectionNamedType && !$returnType->isBuiltin()) {
            $fqcn = $returnType->getName();
            $short = (new ReflectionClass($fqcn))->getShortName();
            if (!isset($this->processedModels[$short])) {
                $this->components['schemas'][$short] = $this->generateModelSchema($fqcn);
                $this->processedModels[$short] = true;
            }
            $schema = ['$ref' => "#/components/schemas/$short"];
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
            if ($type->isBuiltin()) {
                $schema['properties'][$name] = ['type' => $this->mapType($typeName)];
            } else {
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
        // extend: method-level security attribute or global config
        return null;
    }

    protected function getExternalDocs(ReflectionMethod $method): ?array
    {
        return null;
    }
}
