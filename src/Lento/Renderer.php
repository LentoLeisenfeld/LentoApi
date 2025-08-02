<?php

namespace Lento;

use RuntimeException;

/**
 * Undocumented class
 */
final class Renderer
{
    /**
     * Undocumented variable
     *
     * @var RendererOptions
     */
    public static ?RendererOptions $options;

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public static function configure(RendererOptions|array $options): void
    {
        self::$options = $options instanceof RendererOptions
            ? $options
            : new RendererOptions($options);

        self::$options->directory = dirname(realpath($_SERVER['SCRIPT_FILENAME'])) . '/' . rtrim(self::$options->directory, '/\\');

    }
}
