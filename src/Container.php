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
            self::$instances[$name] = self::$services[$name]();
        }
        return self::$instances[$name];
    }
}
