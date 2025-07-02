<?php

namespace Lento\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Route {
    public function __construct(public ?string $name = null) {}
}