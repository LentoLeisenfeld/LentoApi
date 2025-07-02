<?php

namespace Lento\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Param {
    public function __construct(
        public string $source = 'route', // 'route', 'query', 'body'
        public ?string $name = null      // name override
    ) {}
}
