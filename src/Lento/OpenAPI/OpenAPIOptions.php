<?php

namespace Lento\OpenAPI;

/**
 * Undocumented class
 */
class OpenAPIOptions
{
    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $title = 'API Documentation';

    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $version = '1.0.0';

    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $description = 'Generated API documentation';

    // Optional extras for future extension
    /**
     * Undocumented variable
     *
     * @var array
     */
    public array $servers = [];

    /**
     * Undocumented variable
     *
     * @var array
     */
    public array $tags = [];

    /**
     * Undocumented variable
     *
     * @var array<string, mixed>
     */
    public array $securitySchemes = [];

    /**
     * Undocumented variable
     *
     * @var array<int, array<string, array>>
     */
    public array $security = [];

    /**
     * Undocumented variable
     *
     * @var array{description?: string, url?: string}|null
     */
    public ?array $externalDocs = null;

    /**
     * Undocumented function
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Undocumented function
     *
     * @return array
     */
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
