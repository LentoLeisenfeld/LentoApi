<?php

namespace Lento\OpenAPI\Attributes;

use Attribute;

/**
 *
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Tags
{
    /**
     * Undocumented function
     *
     * @param array $text
     */
    public function __construct(
        /**
         * Undocumented variable
         *
         * @var array
         */
        public array $tags = []
    ) {
    }
}
