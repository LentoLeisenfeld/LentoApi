<?php

namespace Lento\OpenAPI\Attributes;

use Attribute;

/**
 *
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Schema
{
    /**
     * Undocumented function
     *
     * @param string|null $name
     */
    public function __construct(
        /**
         * Undocumented variable
         *
         * @var string|null
         */
        public ?string $name = null
    ) {
    }
}
