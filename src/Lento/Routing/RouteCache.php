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
            if (!class_exists($controller)) {
                continue;
            }

            $ref = new \ReflectionClass($controller);
            $file = $ref->getFileName();
            if (!file_exists($file)) {
                return false;
            }

            $currentMTime = filemtime($file);
            if (!isset($storedMeta[$file]) || $storedMeta[$file] !== $currentMTime) {
                return false;
            }
        }

        return true;
    }

    public static function loadToRouter(\Lento\Routing\Router $router): void
    {
        if (!file_exists(self::CACHE_FILE))
            return;
        $data = require self::CACHE_FILE;
        $router->importCacheData($data);
    }

    public static function storeFromRouter(\Lento\Routing\Router $router): void
    {
        $data = $router->exportCacheData();
        $header = "<?php\n// AUTO-GENERATED FILE - DO NOT EDIT\n\n";
        file_put_contents(self::CACHE_FILE, $header . 'return ' . var_export($data, true) . ';');
    }
}
