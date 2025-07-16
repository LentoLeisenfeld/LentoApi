<?php

namespace Lento\OpenAPI\Attributes;

use Attribute;

/**
 *
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Summary
{
    /**
     * Undocumented function
     *
     * @param string|null $text
     */
    public function __construct(
        /**
         * Undocumented variable
         *
         * @var string|null
         */
        public ?string $text = null
    ) {
    }
}
