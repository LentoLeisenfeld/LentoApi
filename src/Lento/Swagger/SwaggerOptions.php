<?php

namespace Lento\Swagger;

class SwaggerOptions
{
    public string $title = 'API Documentation';
    public string $version = '1.0.0';
    public string $description = 'Generated API documentation';

    // Optional extras for future extension
    public array $servers = [];
    public array $tags = [];

    /** @var array<string, mixed> */
    public array $securitySchemes = [];

    /** @var array<int, array<string, array>> */
    public array $security = [];

    /** @var array{description?: string, url?: string}|null */
    public ?array $externalDocs = null;

    public function __construct(array $options = [])
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function toArray(): array
    {
        $info = [
            'title' => $this->title,
            'version' => $this->version,
            'description' => $this->description,
        ];

        $result = [
            'info' => $info,
        ];

        if (!empty($this->servers)) {
            $result['servers'] = $this->servers;
        }

        if (!empty($this->tags)) {
            $result['tags'] = $this->tags;
        }

        if (!empty($this->securitySchemes)) {
            $result['components']['securitySchemes'] = $this->securitySchemes;
        }

        if (!empty($this->security)) {
            $result['security'] = $this->security;
        }

        if ($this->externalDocs !== null) {
            $result['externalDocs'] = $this->externalDocs;
        }

        return $result;
    }
}
