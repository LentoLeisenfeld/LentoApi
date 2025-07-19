<?php

namespace Lento;

use Lento\Routing\Router;

/**
 * High-performance cache for boot-time precompilation and attribute discovery.
 */
class Cache
{
    private const ROUTES_FILE = 'routes.php';
    private const META_FILE = 'meta.php';
    private const ATTRIBUTES_FILE = 'attributes.php';

    public static ?string $directory = null;

    public static function configure(string $directory): void
    {
        self::$directory = rtrim($directory, '/\\');
    }

    public static function getDirectory(): string
    {
        return self::$directory ?: (sys_get_temp_dir() . '/lentocache');
    }

    public static function getRouteFile(): string
    {
        return self::getDirectory() . '/' . self::ROUTES_FILE;
    }

    public static function getMetaFile(): string
    {
        return self::getDirectory() . '/' . self::META_FILE;
    }

    public static function getAttributesFile(): string
    {
        return self::getDirectory() . '/' . self::ATTRIBUTES_FILE;
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
            if (!class_exists($controller)) continue;
            $rc = new \ReflectionClass($controller);
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
        $dir = self::getDirectory();
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $data = $router->exportCompiledPlans();
        $data['services'] = $serviceClasses;

        $header = "<?php\n// AUTO-GENERATED FILE - DO NOT EDIT\n\n";
        file_put_contents($dir . '/' . self::ROUTES_FILE, $header . 'return ' . var_export($data, true) . ';');

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

        self::storeAttributes($controllers);
    }

    public static function storeAttributes(array $controllers): void
    {
        $dir = self::getDirectory();
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $attributes = \Lento\exportAllAttributes($controllers);

        $header = "<?php\n// AUTO-GENERATED FILE - DO NOT EDIT\n\n";
        file_put_contents($dir . '/' . self::ATTRIBUTES_FILE, $header . 'return ' . var_export($attributes, true) . ';');
    }

    public static function loadAttributes(): array
    {
        $file = self::getAttributesFile();
        if (!file_exists($file)) return [];
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

/**
 * Utility to extract all attributes (with args) per class, method, property, and parameter.
 */
if (!function_exists('Lento\\exportAllAttributes')) {
    function exportAllAttributes(array $controllers): array
    {
        $result = [];
        foreach ($controllers as $className) {
            if (!class_exists($className)) continue;
            $rc = new \ReflectionClass($className);

            // Class-level attributes
            $result[$className]['__class'] = array_map(
                fn($attr) => [
                    'name' => $attr->getName(),
                    'args' => $attr->getArguments(),
                ],
                $rc->getAttributes()
            );

            // Property attributes
            foreach ($rc->getProperties() as $prop) {
                $result[$className]['properties'][$prop->getName()] = array_map(
                    fn($attr) => [
                        'name' => $attr->getName(),
                        'args' => $attr->getArguments(),
                    ],
                    $prop->getAttributes()
                );
            }

            // Method and parameter attributes
            foreach ($rc->getMethods() as $method) {
                $result[$className]['methods'][$method->getName()]['__method'] = array_map(
                    fn($attr) => [
                        'name' => $attr->getName(),
                        'args' => $attr->getArguments(),
                    ],
                    $method->getAttributes()
                );
                foreach ($method->getParameters() as $param) {
                    $result[$className]['methods'][$method->getName()]['parameters'][$param->getName()] = array_map(
                        fn($attr) => [
                            'name' => $attr->getName(),
                            'args' => $attr->getArguments(),
                        ],
                        $param->getAttributes()
                    );
                }
            }
        }
        return $result;
    }
}
