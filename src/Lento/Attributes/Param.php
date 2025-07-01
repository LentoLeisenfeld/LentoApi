<?php

namespace Lento\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Param
{
    public function __construct(public ?string $name = null)
    {
    }
}
