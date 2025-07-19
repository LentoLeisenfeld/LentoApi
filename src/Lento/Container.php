<?php

namespace Lento;

use ReflectionClass;
use Throwable;

use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerInterface;

use Lento\Exceptions\{ContainerException, NotFoundException};

/**
 * Undocumented class
 */
class Container implements ContainerInterface
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
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function get(string $id)
    {
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }
        if (class_exists($id)) {
            $reflect = new ReflectionClass($id);
            try {
                if (!$reflect->getConstructor() || $reflect->getConstructor()->getNumberOfRequiredParameters() === 0) {
                    $instance = $reflect->newInstance();
                    $this->services[$id] = $instance;
                    return $instance;
                }
            } catch (Throwable $e) {
                throw new ContainerException($e->getMessage(), 0, $e);
            }
        }
        throw new NotFoundException("Service '$id' not found");
    }

    /**
     * Undocumented function
     *
     * @param string $class
     * @return boolean
     */
    public function has(string $class): bool
    {
        try {
            $this->get($class);
            return true;
        } catch (NotFoundExceptionInterface $e) {
            return false;
        }
    }
}
