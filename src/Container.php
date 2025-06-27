<?php
namespace Lento;

class Container {
    private static $services = [];
    private static $instances = [];

    public static function register(string $name, callable $factory) {
        self::$services[$name] = $factory;
    }

    public static function get(string $name) {
        if (!isset(self::$instances[$name])) {
            if (!isset(self::$services[$name])) {
                throw new \Exception("Service '$name' not registered");
            }

            $instance = self::$services[$name]();

            // Handle #[Inject] properties
            $ref = new \ReflectionClass($instance);
            foreach ($ref->getProperties() as $property) {
                $attributes = $property->getAttributes(\Lento\Attributes\Inject::class);
                if (!empty($attributes)) {
                    $dependencyClass = $property->getType()->getName();
                    $dependency = self::get($dependencyClass);
                    var_dump($dependency);
                    $property->setAccessible(true);
                    $property->setValue($instance, $dependency);
                }
            }

            self::$instances[$name] = $instance;
        }

        return self::$instances[$name];
    }

}
