<?php

declare(strict_types=1);

namespace appgallery\uperms\storage;

use InvalidArgumentException;

/**
 * Instancia el backend de storage correcto según la config.
 * Main.php solo llama StorageFactory::create($config) y ya.
 */
final class StorageFactory{

    public const YAML = 'yaml';
    public const JSON = 'json';
    public const SQLITE = 'sqlite';
    public const MYSQL = 'mysql';

    /**
     * @param array<string, mixed> $config El array de config.yml completo
     */
    public static function create(array $config, string $dataPath): IStorage{
        $type = strtolower((string)($config['storage']['type'] ?? self::YAML));

        return match ($type) {
            self::YAML => new YamlStorage($dataPath),
            self::JSON => new JsonStorage($dataPath),
            self::SQLITE => new SQLiteStorage($dataPath),
            self::MYSQL => self::buildMySQL($config),
            default => throw new InvalidArgumentException(
                "[UltimatePerms] Unknown storage type '{$type}'. " .
                "Valid options: yaml, json, sqlite, mysql."
            ),
        };
    }

    private static function buildMySQL(array $config): MySQLStorage{
        $db = $config['storage']['mysql'] ?? [];

        return new MySQLStorage(
            host: (string)($db['host'] ?? '127.0.0.1'),
            port: (int)($db['port'] ?? 3306),
            user: (string)($db['user'] ?? 'root'),
            password: (string)($db['password'] ?? ''),
            database: (string)($db['database'] ?? 'ultimateperms'),
        );
    }
}