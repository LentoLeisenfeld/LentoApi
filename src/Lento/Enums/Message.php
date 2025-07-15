<?php

namespace Lento\Enums;

// phpcs:disable Generic.Files.LineLength
enum Message: string
{
    /**
     * Undocumented function
     *
     * @param array $vars
     * @return string
     */
    public function format(array $vars = []): string
    {
        $result = $this->value;
        foreach ($vars as $key => $value) {
            $result = str_replace('{' . $key . '}', (string) $value, $result);
        }
        return $result;
    }

    /**
     * Optional: allow named params (PHP 8.1+)
     *
     * @param [type] ...$vars
     * @return string
     */
    public function interpolate(...$vars): string
    {
        // Supports: Message::ControllerNotFound->interpolate(class: $foo, route: $bar)
        return $this->format($vars);
    }

    #region Exceptions
    case Forbidden = "Forbidden";
    case NotFound = "Not Found";
    case Unauthorized = "Unauthorized";
    case ValidationFailed = "Validation failed";
    #endregion

    #region ORM
    case IlluminateNotInstalled = "illuminate/database is not installed. Please run 'composer require illuminate/database'.";
    #endregion

    #region OpenAPI
    case GeneratorPropertyDoesNotExist = 'OpenAPIGenerator: Property type "{property}" does not exist (property "{name}" in class "{rc}")';
    case GeneratorPropertyHasNoType = 'OpenAPIGenerator: Property "{name}" in class "{rc}" has no type.';
    case GeneratorClassDoesNotExist = 'OpenAPIGenerator: generateModelSchema - class "{fqcn}" does not exist.';
    case GeneratorPropertyDoesNotExist2 = 'OpenAPIGenerator: Parameter type "{type}" does not exist in method {method}.';
    #endregion
}
// phpcs:enable Generic.Files.LineLength