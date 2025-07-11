<?php

namespace Lento\OpenAPI\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Throws
{
    public string $exception;
    public int $status;
    public ?string $description;

    public function __construct(string $exception, int $status, ?string $description = null)
    {
        $this->exception = $exception;
        $this->status = $status;
        $this->description = $description;
    }
}
