<?php

namespace Lento\Validation\Attributes;

use Attribute;

/**
 *
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Regex
{
    public function __construct(public string $pattern) {}
}