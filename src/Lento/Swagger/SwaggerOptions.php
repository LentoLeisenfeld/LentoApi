<?php

namespace Lento\Swagger;

class SwaggerOptions {
    public string $title = 'API Documentation';
    public string $version = '1.0.0';
    public string $description = 'Generated API documentation';

    // Optional extras for future extension
    public array $servers = [];
    public array $tags = [];
    public array $security = [];

    public function __construct(array $options = []) {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function toArray(): array {
        return [
            'title' => $this->title,
            'version' => $this->version,
            'description' => $this->description,
        ];
    }
}