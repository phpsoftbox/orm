<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\EntityManagerConfig;
use PhpSoftBox\Orm\Tests\EntityManager\Fixtures\EntityWithoutTableName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
#[CoversClass(EntityManagerConfig::class)]
final class EntityManagerConfigNamingConventionIntegrationTest extends TestCase
{
    /**
     * Проверяет интеграцию: EntityManager по умолчанию использует namingConvention из EntityManagerConfig
     * и может вывести имя таблицы для сущности, у которой #[Entity(table: ...)] не задан.
     */
    #[Test]
    public function entityManagerUsesNamingConventionToResolveTableName(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $em = new EntityManager(
            connection: $conn,
            config: new EntityManagerConfig(enableBuiltInListeners: false),
        );

        $qb = $em->queryFor(EntityWithoutTableName::class);

        $built = $qb->toSql();

        self::assertIsArray($built);
        self::assertArrayHasKey('sql', $built);

        self::assertStringContainsString('FROM "entity_without_table_names"', $built['sql']);
    }
}
