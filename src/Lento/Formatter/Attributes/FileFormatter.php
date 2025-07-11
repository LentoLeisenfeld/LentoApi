<?php

namespace Lento\Formatter\Attributes;

use Attribute;

/**
 *
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class FileFormatter
{
    /**
     * Undocumented function
     *
     * @param string|null $mimetype
     * @param string|null $filename
     */
    public function __construct(
        /**
         * Undocumented variable
         *
         * @var string|null
         */
        public ?string $mimetype = null,
        /**
         * Undocumented variable
         *
         * @var string|null
         */
        public ?string $filename = null,
        /**
         * Undocumented variable
         *
         * @var boolean
         */
        public bool $download = false
    ) {
    }
}
