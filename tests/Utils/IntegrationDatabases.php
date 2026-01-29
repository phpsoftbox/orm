<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Utils;

use PhpSoftBox\Database\Database;
use PhpSoftBox\Database\Driver\DriversEnum;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Хелперы для интеграционных тестов ORM.
 *
 * Важно: пакет ORM не должен зависеть от тестовых хелперов пакета Database,
 * поэтому здесь есть своя минимальная версия, достаточная для in-memory sqlite.
 */
final class IntegrationDatabases
{
    private const string SQLITE_DSN_URL = 'sqlite:///:memory:';

    /**
     * Поднимает in-memory sqlite Database.
     *
     * @throws RuntimeException
     */
    public static function sqliteDatabase(array $config = []): Database
    {
        $config = $config ?: self::getDefaultConfigForDriver(DriversEnum::SQLITE);

        try {
            $db = Database::fromConfig($config);
            $db->fetchOne('SELECT 1');
        } catch (PDOException $e) {
            throw new RuntimeException('Failed to connect to Sqlite database.', 0, $e);
        }

        return $db;
    }

    public static function getDefaultConfigForDriver(DriversEnum $driver): array
    {
        $dsn = match ($driver) {
            DriversEnum::SQLITE => self::SQLITE_DSN_URL,
            default => throw new RuntimeException('Only sqlite is supported in ORM tests helper right now.'),
        };

        return [
            'connections' => [
                'default' => [
                    'dsn' => $dsn,
                    'options' => [
                        PDO::ATTR_TIMEOUT => 2,
                    ],
                ],
            ],
        ];
    }
}

