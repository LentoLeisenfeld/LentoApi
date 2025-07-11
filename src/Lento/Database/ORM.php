<?php

namespace Lento\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Lento\Enums\Message;

/**
 * Undocumented class
 */
final class ORM
{
    /**
     * Undocumented function
     *
     * @param string $dsn
     * @return void
     */
    public static function configure(string $dsn): void
    {
        if (!class_exists(Capsule::class)) {
            throw new \RuntimeException(Message::IlluminateNotInstalled->value);
        }

        $capsule = new Capsule();

        $config = match (true) {
            str_starts_with($dsn, 'sqlite:') => self::parseSqliteDsn($dsn),
            str_starts_with($dsn, 'pgsql:') => self::parsePgsqlDsn($dsn),
            str_starts_with($dsn, 'mysql:') => self::parseMysqlDsn($dsn),
            str_starts_with($dsn, 'sqlsrv:'),
            str_starts_with($dsn, 'mssql:') => self::parseMssqlDsn($dsn),
            default => throw new \InvalidArgumentException("Unsupported DSN: $dsn"),
        };

        $capsule->addConnection($config);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

    /**
     * Undocumented function
     *
     * @param string $dsn
     * @return array
     */
    private static function parseSqliteDsn(string $dsn): array
    {
        // DSN format: sqlite:/path/to/file OR sqlite::memory:
        $path = trim(substr($dsn, 7));
        return [
            'driver' => 'sqlite',
            'database' => $path === ':memory:' ? ':memory:' : $path,
            'prefix' => '',
        ];
    }

    /**
     * Undocumented function
     *
     * @param string $dsn
     * @return array
     */
    private static function parsePgsqlDsn(string $dsn): array
    {
        // DSN format: pgsql:host=localhost;port=5432;dbname=test;user=me;password=secret
        preg_match_all('/(\w+)=([^;]+)/', $dsn, $matches);
        $params = array_combine($matches[1], $matches[2]);

        return [
            'driver' => 'pgsql',
            'host' => $params['host'] ?? 'localhost',
            'port' => (int) ($params['port'] ?? 5432),
            'database' => $params['dbname'] ?? '',
            'username' => $params['user'] ?? '',
            'password' => $params['password'] ?? '',
            'charset' => $params['charset'] ?? 'utf8',
            'prefix' => '',
            'schema' => $params['schema'] ?? 'public',
            'sslmode' => $params['sslmode'] ?? 'prefer',
        ];
    }

    /**
     * Undocumented function
     *
     * @param string $dsn
     * @return array
     */
    private static function parseMysqlDsn(string $dsn): array
    {
        // DSN format: mysql:host=localhost;port=3306;dbname=test;user=root;password=secret;charset=utf8mb4
        preg_match_all('/(\w+)=([^;]+)/', $dsn, $matches);
        $params = array_combine($matches[1], $matches[2]);

        return [
            'driver' => 'mysql',
            'host' => $params['host'] ?? 'localhost',
            'port' => (int) ($params['port'] ?? 3306),
            'database' => $params['dbname'] ?? '',
            'username' => $params['user'] ?? '',
            'password' => $params['password'] ?? '',
            'charset' => $params['charset'] ?? 'utf8mb4',
            'collation' => $params['collation'] ?? 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => (isset($params['strict']) ? (bool) $params['strict'] : true),
            'engine' => $params['engine'] ?? null,
            'options' => [],
        ];
    }

    /**
     * Undocumented function
     *
     * @param string $dsn
     * @return array
     */
    private static function parseMssqlDsn(string $dsn): array
    {
        // Supports both sqlsrv: and mssql:
        // DSN format: sqlsrv:Server=localhost;Database=test;User=sa;Password=secret
        preg_match_all('/(\w+)=([^;]+)/', $dsn, $matches);
        $params = array_combine($matches[1], $matches[2]);

        return [
            'driver' => 'sqlsrv',
            'host' => $params['Server'] ?? $params['host'] ?? 'localhost',
            'port' => (int) ($params['port'] ?? 1433),
            'database' => $params['Database'] ?? $params['dbname'] ?? '',
            'username' => $params['User'] ?? $params['user'] ?? '',
            'password' => $params['Password'] ?? $params['password'] ?? '',
            'charset' => $params['charset'] ?? 'utf8',
            'prefix' => '',
            // Optional: 'trust_server_certificate' => true,
        ];
    }
}
