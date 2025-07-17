<?php

namespace Lento;

use Lento\OpenAPI\OpenAPIOptions;

/**
 * Undocumented class
 */
final class OpenAPI
{
    /**
     * Undocumented variable
     *
     * @var boolean
     */
    private static bool $enabled = false;

    /**
     * Undocumented variable
     *
     * @var OpenAPIOptions
     */
    private static ?OpenAPIOptions $options;

    /**
     * Undocumented function
     */
    private function __construct()
    {
    }

    /**
     * Undocumented function
     *
     * @param OpenAPIOptions|array $options
     * @return void
     */
    public static function configure(OpenAPIOptions|array $options): void
    {
        self::$enabled = true;
        self::$options = $options instanceof OpenAPIOptions
            ? $options
            : new OpenAPIOptions($options);
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public static function getInfo(): array
    {
        return self::$options->toArray()['info'];
    }

    /**
     * Undocumented function
     *
     * @return OpenAPIOptions
     */
    public static function getOptions(): OpenAPIOptions
    {
        return self::$options;
    }

    /**
     * Undocumented function
     *
     * @return array|null
     */
    public static function getExternalDocs(): ?array
    {
        return self::$options->toArray()['externalDocs'];
    }

    public static function addTag(string $name, string $description): self
    {
        self::$options->tags[] = [
            "name" => $name,
            "description" => $description
        ];

        return new self();
    }

    public static function addTags(array $tags): self
    {
        foreach ($tags as [$name, $desc]) {
            self::addTag($name, $desc);
        }

        return new self();
    }

    public static function enable(): self
    {
        self::$enabled = true;

        return new self();
    }
}