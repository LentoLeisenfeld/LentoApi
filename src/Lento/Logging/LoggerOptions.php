<?php

namespace Lento\Logging;

/**
 * Undocumented class
 */
class LoggerOptions
{
    /**
     * Undocumented variable
     *
     * @var string[] List of PSR-3 log levels accepted by this logger, e.g. ['info','error']
     */
    public array $levels = [];

    /**
     * Undocumented variable
     *
     * @var string|null Channel or name (optional)
     */
    public ?string $name = null;

    /**
     * Undocumented function
     *
     * @param array $levels
     * @param string|null $name
     */
    public function __construct(array $levels = [], ?string $name = null)
    {
        $this->levels = $levels;
        $this->name = $name;
    }
}
