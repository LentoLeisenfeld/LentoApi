<?php

namespace Lento\Attributes\Methods;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Get
{
    public function __construct(public string $path)
    {
    }
}
