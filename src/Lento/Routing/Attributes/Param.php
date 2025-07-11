<?php

namespace Lento\Routing\Attributes;

use Attribute;

/**
 *
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Param
{
    /**
     * Undocumented function
     *
     * @param string $source
     * @param string|null $name
     */
    public function __construct(
        /**
         * Undocumented variable
         *
         * @var string
         */
        public string $source = 'route',
        /**
         * Undocumented variable
         *
         * @var string|null
         */
        public ?string $name = null
    ) {
    }
}
