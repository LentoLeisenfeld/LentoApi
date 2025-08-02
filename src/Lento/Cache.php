<?php

namespace Lento;

use ReflectionClass;
use Lento\Routing\Router;
use RuntimeException;

/**
 * High-performance cache for boot-time precompilation and attribute discovery.
 */
class Cache
{
    private const ROUTES_FILE = 'routes.php';
    private const META_FILE = 'meta.php';
    private const ATTRIBUTES_FILE = 'attributes.php';

    public static ?string $public_directory = null;
    public static ?string $cache_directory = null;

    public static function setCacheDirectory(string $directory): self
    {
        self::$cache_directory = rtrim($directory, '/\\');

        return new self();
    }
    public static function setPublicDirectory(string $directory): self
    {
        self::$public_directory = rtrim($directory, '/\\');

        return new self();
    }

    public static function getCacheDirectory(): string
    {
        return self::$cache_directory ?: (sys_get_temp_dir() . '/lentocache');
    }
    public static function getPublicDirectory(): string
    {
        return self::$public_directory ?: throw new RuntimeException("ToDo: error message");
    }

    public static function getRouteFile(): string
    {
        return self::getCacheDirectory() . '/' . self::ROUTES_FILE;
    }

    public static function getMetaFile(): string
    {
        return self::getCacheDirectory() . '/' . self::META_FILE;
    }

    public static function getAttributesFile(): string
    {
        return self::getCacheDirectory() . '/' . self::ATTRIBUTES_FILE;
    }

    public static function isAvailable(array $controllers): bool
    {
        $routeFile = self::getRouteFile();
        $metaFile = self::getMetaFile();
        $attributesFile = self::getAttributesFile();

        foreach ([$routeFile, $metaFile, $attributesFile] as $file) {
            if (!is_string($file) || !is_file($file)) {
                return false;
            }
        }

        $storedMeta = @require $metaFile;
        if (!is_array($storedMeta)) {
            return false;
        }

        foreach ($controllers as $controller) {
            if (!class_exists($controller)) {
                continue;
            }

            $rc = new ReflectionClass($controller);
            $file = $rc->getFileName();

            if (!$file || !file_exists($file)) {
                return false;
            }

            $mtime = filemtime($file);

            if (!isset($storedMeta[$file]) || $storedMeta[$file] !== $mtime) {
                return false;
            }
        }
        return true;
    }

    public static function storeFromRouter(Router $router, array $controllers, array $serviceClasses): void
    {
        $dir = self::getCacheDirectory();
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $data = $router->exportCompiledPlans();
        $data['services'] = $serviceClasses;

        $header = "<?php\n// AUTO-GENERATED FILE - DO NOT EDIT\n\n";
        file_put_contents($dir . '/' . self::ROUTES_FILE, $header . 'return ' . var_export($data, true) . ';');

        $meta = [];
        foreach ($controllers as $controller) {
            if (!class_exists($controller))
                continue;
            $rc = new ReflectionClass($controller);
            $file = $rc->getFileName();
            if ($file && file_exists($file)) {
                $meta[$file] = filemtime($file);
            }
        }
        file_put_contents($dir . '/' . self::META_FILE, $header . 'return ' . var_export($meta, true) . ';');

        self::storeAttributes($controllers);
    }

    public static function storeAttributes(array $controllers): void
    {
        $dir = self::getCacheDirectory();
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $attributes = Router::exportAllAttributes($controllers);

        $header = "<?php\n// AUTO-GENERATED FILE - DO NOT EDIT\n\n";
        file_put_contents($dir . '/' . self::ATTRIBUTES_FILE, $header . 'return ' . var_export($attributes, true) . ';');
    }

    public static function loadAttributes(): array
    {
        $file = self::getAttributesFile();
        if (!file_exists($file)) {
            return [];
        }

        return require $file;
    }

    public static function loadToRouter(Router $router): void
    {
        $routeFile = self::getRouteFile();
        if (!file_exists($routeFile)) {
            return;
        }
        $data = require $routeFile;
        $router->importCompiledPlans($data);
    }
}
