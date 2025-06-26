<?php
namespace Lento\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Schema {
    public function __construct(public ?string $name = null) {}
}
