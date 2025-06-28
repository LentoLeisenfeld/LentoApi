<?php

namespace Lento;

use Illuminate\Database\Capsule\Manager as Capsule;

final class ORM
{
    public static function configure(string $dsn): void
    {
        $capsule = new Capsule;

        $config = match (true) {
            str_starts_with($dsn, 'sqlite:') => [
                'driver'   => 'sqlite',
                'database' => substr($dsn, 7),
                'prefix'   => '',
            ],
            str_starts_with($dsn, 'pgsql:') => self::parsePgsqlDsn($dsn),
            str_starts_with($dsn, 'mysql:') => self::parseMysqlDsn($dsn),
            default => throw new InvalidArgumentException("Unsupported DSN: $dsn"),
        };

        $capsule->addConnection($config);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

    private static function parsePgsqlDsn(string $dsn): array
    {
        preg_match_all('/(\w+)=([^;]+)/', $dsn, $matches);
        $params = array_combine($matches[1], $matches[2]);

        return [
            'driver'    => 'pgsql',
            'host'      => $params['host'] ?? 'localhost',
            'port'      => $params['port'] ?? 5432,
            'database'  => $params['dbname'] ?? '',
            'username'  => $params['user'] ?? '',
            'password'  => $params['password'] ?? '',
            'charset'   => 'utf8',
            'prefix'    => '',
            'schema'    => 'public',
            'sslmode'   => 'prefer',
        ];
    }

    private static function parseMysqlDsn(string $dsn): array
    {
        // Optional: implement MySQL parsing here
        return [];
    }
}
