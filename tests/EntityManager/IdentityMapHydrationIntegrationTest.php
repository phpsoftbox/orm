<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Tests\Repository\Fixtures\TestEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityManager::class)]
final class IdentityMapHydrationIntegrationTest extends TestCase
{
    #[Test]
    public function queryHydrationReusesManagedInstance(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $connection = new Connection($pdo, new SqliteDriver());

        $connection->execute('CREATE TABLE test_entities (id INTEGER PRIMARY KEY, name TEXT)');
        $connection->execute("INSERT INTO test_entities (id, name) VALUES (1, 'One')");

        $entityManager = new EntityManager($connection);

        $fromFind  = $entityManager->find(TestEntity::class, 1);
        $fromQuery = $entityManager->queryFor(TestEntity::class)->fetchEntity();
        $fromList  = $entityManager->queryFor(TestEntity::class)->fetchEntities()->first();

        self::assertNotNull($fromFind);
        self::assertSame($fromFind, $fromQuery);
        self::assertSame($fromFind, $fromList);
    }
}
