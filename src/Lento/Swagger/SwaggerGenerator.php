<?php
namespace Lento\Swagger;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Lento\Attributes\{Ignore, Property, Param};
use Lento\Routing\Router;
use Lento\Swagger;

class SwaggerGenerator {
    private Router $router;
    private array $processedModels = [];
    private array $components = ['schemas' => []];

    public function __construct(Router $router) {
        $this->router = $router;
    }

    public function generate(): array {
        return [
            'openapi' => '3.0.0',
            'info' => Swagger::getInfo(),
            'paths' => $this->buildPaths(),
            'components' => $this->components,
        ];
    }

    protected function buildPaths(): array {
        $paths = [];

        foreach ($this->router->getRoutes() as $route) {
            if (!is_array($route->handler) || count($route->handler) !== 2) continue;

            [$controller, $methodName] = $route->handler;

            $refClass = new ReflectionClass($controller);
            $refMethod = $refClass->getMethod($methodName);

            if ($this->isIgnored($refClass, $refMethod)) continue;

            $method = strtolower($route->method);
            $path = $route->path;

            $paths[$path][$method] = $this->buildOperation($refMethod);
        }

        return $paths;
    }

    protected function isIgnored(ReflectionClass $refClass, ReflectionMethod $refMethod): bool {
        return !empty($refClass->getAttributes(Ignore::class)) || !empty($refMethod->getAttributes(Ignore::class));
    }

    protected function buildOperation(ReflectionMethod $method): array {
        [$parameters, $requestBody, $schemas] = $this->extractParameters($method);

        foreach ($schemas as $name => $fqcn) {
            if (!isset($this->processedModels[$name])) {
                $this->components['schemas'][$name] = $this->generateModelSchema($fqcn);
                $this->processedModels[$name] = true;
            }
        }

        $responseSchema = $this->getResponseSchema($method);

        $operation = [
            'summary' => $method->getName(),
            'parameters' => $parameters,
            'responses' => [
                '200' => [
                    'description' => 'Successful response',
                    'content' => [
                        'application/json' => [
                            'schema' => $responseSchema,
                        ],
                    ],
                ],
            ],
        ];

        if ($requestBody) {
            $operation['requestBody'] = $requestBody;
        }

        return $operation;
    }

    protected function getResponseSchema(ReflectionMethod $method): array {
        $returnType = $method->getReturnType();

        if ($returnType instanceof ReflectionNamedType && !$returnType->isBuiltin()) {
            $fqcn = $returnType->getName();
            $short = (new ReflectionClass($fqcn))->getShortName();

            if (!isset($this->processedModels[$short])) {
                $this->components['schemas'][$short] = $this->generateModelSchema($fqcn);
                $this->processedModels[$short] = true;
            }

            return ['$ref' => "#/components/schemas/$short"];
        }

        return ['type' => 'object'];
    }

    protected function extractParameters(ReflectionMethod $method): array {
        $params = [];
        $requestBody = null;
        $schemas = [];

        foreach ($method->getParameters() as $param) {
            $hasParamAttr = !empty($param->getAttributes(Param::class));
            $paramType = $param->getType();

            if (!$paramType instanceof ReflectionNamedType) continue;

            $typeName = $paramType->getName();

            if (!$paramType->isBuiltin()) {
                $short = (new ReflectionClass($typeName))->getShortName();
                $schemas[$short] = $typeName;

                $requestBody = [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                '$ref' => "#/components/schemas/$short",
                            ],
                        ],
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

    protected function generateModelSchema(string $fqcn): array {
        $rc = new ReflectionClass($fqcn);

        $schema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];

        foreach ($rc->getProperties() as $prop) {
            if (!$prop->isPublic() || empty($prop->getAttributes(Property::class))) continue;

            $name = $prop->getName();
            $type = $prop->getType();

            if (!$type instanceof ReflectionNamedType) continue;
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

    protected function mapType(string $phpType): string {
        return match ($phpType) {
            'int' => 'integer',
            'float', 'double' => 'number',
            'bool' => 'boolean',
            default => 'string',
        };
    }
}
