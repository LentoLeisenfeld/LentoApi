<?php

namespace Lento\Logging;

class LoggerOptions
{
    /**
     * @var string[] List of PSR-3 log levels accepted by this logger, e.g. ['info','error']
     */
    public array $levels = [];

    /** @var string|null Channel or name (optional) */
    public ?string $name = null;

    public function __construct(array $levels = [], ?string $name = null)
    {
        $this->levels = $levels;
        $this->name = $name;
    }
}
