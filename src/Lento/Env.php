<?php

namespace Lento;

/**
 * Undocumented class
 */
final class Env
{
    /**
     * Undocumented function
     *
     * @param [type] $dir
     * @return void
     */
    public static function load(string $dir = __DIR__): void
    {
        $basePath = rtrim($dir, '/\\');

        // 1. Load base env
        self::parseFile("$basePath/.env");

        // 2. Detect environment
        $env = $_ENV['APP_ENV']
            ?? $_SERVER['APP_ENV']
            ?? self::detectEnvironment();

        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = $env;

        // 3. Load env-specific file
        $envFile = "$basePath/.env.$env";
        if (is_file($envFile)) {
            self::parseFile($envFile);
        }
    }

    /**
     * Undocumented function
     *
     * @param string $file
     * @return void
     */
    private static function parseFile(string $file): void
    {
        $handle = @fopen($file, 'r');
        if (!$handle) {
            return;
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $equals = strpos($line, '=');
            if ($equals === false) {
                continue;
            }

            $key = trim(substr($line, 0, $equals));
            $val = trim(substr($line, $equals + 1), " \t\n\r\0\x0B\"'");

            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $val;
            }
            if (!isset($_SERVER[$key])) {
                $_SERVER[$key] = $val;
            }
            if (getenv($key) === false) {
                putenv("$key=$val");
            }
        }

        fclose($handle);
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    private static function detectEnvironment(): string
    {
        static $result;

        if ($result !== null) {
            return $result;
        }

        if (php_sapi_name() === 'cli' || php_sapi_name() === 'cli-server') {
            return $result = 'development';
        }

        if (!empty($_SERVER['SERVER_NAME']) && str_contains($_SERVER['SERVER_NAME'], 'localhost')) {
            return $result = 'development';
        }

        if (extension_loaded('xdebug')) {
            return $result = 'development';
        }

        return $result = 'production';
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public static function isDev(): bool
    {
        return ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production') === 'development';
    }

    /**
     * Undocumented function
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}
