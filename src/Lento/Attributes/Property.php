<?php

namespace Lento\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Property
{
    public function __construct(Role $role)
    {
    }
}
