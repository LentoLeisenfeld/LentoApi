<?php

namespace Lento\Routing\Attributes;

use Attribute;

/**
 *
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Query
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
