<?php

namespace Lento;

use ReflectionClass;
use Exception;

/**
 * Undocumented class
 */
class Container
{
    /**
     * Undocumented variable
     *
     * @var array<string,object>
     */
    private array $services = [];

    /**
     * Register a service instance under its class name.
     *
     * @param object $service
     */
    public function set(object $service): void
    {
        $this->services[get_class($service)] = $service;
    }

    /**
     * Get a service by class name. Auto-instantiate if not registered.
     *
     * @template T
     * @param class-string<T> $className
     * @return T
     * @throws Exception
     */
    public function get(string $className)
    {
        // Return existing
        if (isset($this->services[$className])) {
            return $this->services[$className];
        }
        // Auto-wire concrete classes
        if (class_exists($className)) {
            $reflect = new ReflectionClass($className);
            // For simple classes without constructor or with optional params
            if (!$reflect->getConstructor() || $reflect->getConstructor()->getNumberOfRequiredParameters() === 0) {
                $instance = $reflect->newInstance();
                $this->services[$className] = $instance;
                return $instance;
            }
        }
        throw new Exception("Service '$className' not registered");
    }

    public function has(string $class): bool
    {
        try {
            $this->get($class);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
