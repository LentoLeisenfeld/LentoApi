<?php
namespace Lento;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Lento\Attributes\{Ignore, Property, Param};

class SwaggerGenerator {
    private Router $router;
    private array $processedModels = [];
    private array $components = ['schemas' => []];

    public function __construct(Router $router) {
        $this->router = $router;
    }

    public function generate(): array {
        $swagger = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'API Documentation',
                'version' => '1.0.0',
            ],
            'paths' => [],
        ];

        foreach ($this->router->getRoutes() as $route) {
            $method = $route->method ?? $route[0];
            $path = $route->path ?? $route[1];
            $handler = $route->handler ?? $route[2];

            if (!is_array($handler) || count($handler) !== 2) continue;

            [$controller, $methodName] = $handler;

            $refClass = new ReflectionClass($controller);
            $refMethod = $refClass->getMethod($methodName);

            // Skip ignored classes/methods
            if (!empty($refClass->getAttributes(Ignore::class))) continue;
            if (!empty($refMethod->getAttributes(Ignore::class))) continue;

            // Extract request input and register schemas
            [$parameters, $requestBody, $schemas] = $this->extractParameters($refMethod);
            foreach ($schemas as $name => $fqcn) {
                if (!isset($this->processedModels[$name])) {
                    $this->components['schemas'][$name] = $this->generateModelSchema($fqcn);
                    $this->processedModels[$name] = true;
                }
            }

            // Return type
            $responseSchema = ['type' => 'object'];
            $returnType = $refMethod->getReturnType();
            if ($returnType instanceof ReflectionNamedType && !$returnType->isBuiltin()) {
                $returnFQCN = $returnType->getName();
                $shortName = (new ReflectionClass($returnFQCN))->getShortName();

                $responseSchema = ['$ref' => "#/components/schemas/$shortName"];
                if (!isset($this->processedModels[$shortName])) {
                    $this->components['schemas'][$shortName] = $this->generateModelSchema($returnFQCN);
                    $this->processedModels[$shortName] = true;
                }
            }

            // Build path operation
            $operation = [
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

            $swagger['paths'][$path][strtolower($method)] = $operation;
        }

        $swagger['components'] = $this->components;
        return $swagger;
    }

    private function extractParameters(ReflectionMethod $method): array {
        $params = [];
        $requestBody = null;
        $schemas = [];

        foreach ($method->getParameters() as $param) {
            $paramAttrs = $param->getAttributes(Param::class);
            if (empty($paramAttrs)) continue;

            $paramType = $param->getType();
            if (!$paramType instanceof ReflectionNamedType) continue;

            $typeName = $paramType->getName();

            if (!$paramType->isBuiltin()) {
                $shortName = (new ReflectionClass($typeName))->getShortName();
                $requestBody = [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                '$ref' => "#/components/schemas/$shortName",
                            ],
                        ],
                    ],
                ];
                $schemas[$shortName] = $typeName;
            } else {
                $name = $param->getName();
                $params[] = [
                    'name' => $name,
                    'in' => 'path',
                    'required' => true,
                    'schema' => [
                        'type' => $this->mapType($typeName),
                    ],
                ];
            }
        }

        return [$params, $requestBody, $schemas];
    }

    private function generateModelSchema(string $fqcn): array {
        // Skip primitives
        if (in_array($fqcn, ['int', 'float', 'bool', 'string', 'array', 'mixed'])) {
            return ['type' => $this->mapType($fqcn)];
        }

        $rc = new ReflectionClass($fqcn);
        $schema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];

        foreach ($rc->getProperties() as $prop) {
            if (!$prop->isPublic()) continue;
            if (empty($prop->getAttributes(Property::class))) continue;

            $propName = $prop->getName();
            $typeObj = $prop->getType();

            if (!$typeObj instanceof ReflectionNamedType) continue;
            $typeName = $typeObj->getName();

            if ($typeObj->isBuiltin()) {
                $schema['properties'][$propName] = [
                    'type' => $this->mapType($typeName),
                ];
            } else {
                $short = (new ReflectionClass($typeName))->getShortName();
                $schema['properties'][$propName] = [
                    '$ref' => "#/components/schemas/$short",
                ];

                if (!isset($this->processedModels[$short])) {
                    $this->components['schemas'][$short] = $this->generateModelSchema($typeName);
                    $this->processedModels[$short] = true;
                }
            }

            $schema['required'][] = $propName;
        }

        return $schema;
    }

    private function mapType(string $phpType): string {
        return match ($phpType) {
            'int' => 'integer',
            'float', 'double' => 'number',
            'bool' => 'boolean',
            default => 'string',
        };
    }
}
