<?php

namespace Lento\Routing;

class RouteCache
{
    private const CACHE_FILE = __DIR__ . '/../../cache/routes.php';
    private const META_FILE = __DIR__ . '/../../cache/routes.meta.php';

    /**
     * Check if cache is still valid.
     */
    public static function isAvailable(array $controllers): bool
    {
        if (!file_exists(self::CACHE_FILE) || !file_exists(self::META_FILE)) {
            return false;
        }

        $storedMeta = require self::META_FILE;
        foreach ($controllers as $controller) {
            if (!class_exists($controller)) continue;

            $ref = new \ReflectionClass($controller);
            $file = $ref->getFileName();
            if (!file_exists($file)) return false;

            $currentMTime = filemtime($file);
            if (!isset($storedMeta[$file]) || $storedMeta[$file] !== $currentMTime) {
                return false;
            }
        }

        return true;
    }

    public static function load(): array
    {
        return require self::CACHE_FILE;
    }

    public static function store(array $routes, array $controllers): void
    {
        if (!is_dir(dirname(self::CACHE_FILE))) {
            mkdir(dirname(self::CACHE_FILE), 0777, true);
        }

        $exported = array_map(fn($route) => $route->toArray(), $routes);

        $header = "<?php\n// AUTO-GENERATED FILE - DO NOT EDIT\n\n";
        file_put_contents(self::CACHE_FILE, $header . 'return ' . var_export($exported, true) . ';');

        $meta = [];
        foreach ($controllers as $controller) {
            $ref = new \ReflectionClass($controller);
            $file = $ref->getFileName();
            if (file_exists($file)) {
                $meta[$file] = filemtime($file);
            }
        }

        $header = "<?php\n// AUTO-GENERATED FILE - DO NOT EDIT\n\n";
        file_put_contents(self::META_FILE, $header . 'return ' . var_export($meta, true) . ';');
    }
}
