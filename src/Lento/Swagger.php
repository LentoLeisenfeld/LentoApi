<?php
namespace Lento;

use Lento\Swagger\SwaggerOptions;

final class Swagger {
    private static bool $enabled = false;
    private static SwaggerOptions $options;

    private function __construct() {}

    public static function configure(SwaggerOptions|array $options): void {
        self::$enabled = true;
        self::$options = $options instanceof SwaggerOptions
            ? $options
            : new SwaggerOptions($options);
    }

    public static function isEnabled(): bool {
        return self::$enabled;
    }

    public static function getInfo(): array {
        return self::$options->toArray();
    }

    public static function getOptions(): SwaggerOptions {
        return self::$options;
    }
}
