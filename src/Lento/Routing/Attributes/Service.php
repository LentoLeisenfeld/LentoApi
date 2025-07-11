<?php

namespace Lento\Routing\Attributes;

use Attribute;

/**
 * Marks a class as a service for automatic registration in the DI container.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Service
{
    /**
     * Optional alias for the service (defaults to class name).
     *
     * @var string|null
     */
    public ?string $alias;

    /**
     * Undocumented Variable
     *
     * @param string|null $alias An optional alias to register the service under.
     */
    public function __construct(?string $alias = null)
    {
        $this->alias = $alias;
    }

    /**
     * Get the service alias, or the class name if none provided.
     *
     * @param string $className
     * @return string
     */
    public function getAlias(string $className): string
    {
        return $this->alias ?? $className;
    }
}
