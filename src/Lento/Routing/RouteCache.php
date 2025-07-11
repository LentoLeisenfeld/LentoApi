<?php

namespace Lento\Routing;

/**
 * Undocumented class
 */
class RouteCache
{
    /**
     * Undocumented variable
     *
     * @var string
     */
    private static ?string $cacheDir = null;

    /**
     *
     */
    private const ROUTE_FILE = 'routes.php';

    /**
     *
     */
    private const META_FILE = 'routes.meta.php';

    /**
     * Undocumented function
     *
     * @param string $dir
     * @return void
     */
    public static function setDirectory(string $dir): void
    {
        self::$cacheDir = rtrim($dir, '/\\');
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public static function getDirectory(): string
    {
        return self::$cacheDir ?: (sys_get_temp_dir() . '/lentocache');
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public static function getDefaultRouteFile(): string
    {
        return self::getDirectory() . '/' . self::ROUTE_FILE;
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public static function getDefaultMetaFile(): string
    {
        return self::getDirectory() . '/' . self::META_FILE;
    }

    /**
     * Check if the cache is still valid based on mtime of controllers.
     *
     * @param array $controllers
     * @return boolean
     */
    public static function isAvailable(array $controllers): bool
    {
        $routeFile = self::getDefaultRouteFile();
        $metaFile = self::getDefaultMetaFile();

        if (!file_exists($routeFile) || !file_exists($metaFile)) {
            return false;
        }

        $storedMeta = @require $metaFile;
        if (!is_array($storedMeta)) return false;

        foreach ($controllers as $controller) {
            if (!class_exists($controller)) continue;
            $rc = new \ReflectionClass($controller);
            $file = $rc->getFileName();
            if (!$file || !file_exists($file)) return false;
            $mtime = filemtime($file);
            if (!isset($storedMeta[$file]) || $storedMeta[$file] !== $mtime) {
                return false;
            }
        }
        return true;
    }

    /**
      * Loads the routes from cache into the router.
      *
      * @param Router $router
      * @return void
      */
    public static function loadToRouter(Router $router): void
    {
        $routeFile = self::getDefaultRouteFile();
        if (!file_exists($routeFile)) return;
        $data = require $routeFile;
        $router->importCacheData($data);
    }

    /**
     * Exports router route specs and stores them, and writes meta file.
     *
     * @param Router $router
     * @param array $controllers
     * @return void
     */
    public static function storeFromRouter(Router $router, array $controllers): void
    {
        $dir = self::getDirectory();
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $data = $router->exportCacheData();
        $header = "<?php\n// AUTO-GENERATED FILE - DO NOT EDIT\n\n";
        file_put_contents($dir . '/' . self::ROUTE_FILE, $header . 'return ' . var_export($data, true) . ';');

        $meta = [];
        foreach ($controllers as $controller) {
            if (!class_exists($controller)) continue;
            $rc = new \ReflectionClass($controller);
            $file = $rc->getFileName();
            if ($file && file_exists($file)) {
                $meta[$file] = filemtime($file);
            }
        }
        file_put_contents($dir . '/' . self::META_FILE, $header . 'return ' . var_export($meta, true) . ';');
    }
}
