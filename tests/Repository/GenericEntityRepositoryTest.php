<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Repository;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\DatabaseLookup\LookupSpec;
use PhpSoftBox\Orm\Repository\GenericEntityRepository;
use PhpSoftBox\Orm\Tests\Repository\Fixtures\TestEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(GenericEntityRepository::class)]
final class GenericEntityRepositoryTest extends TestCase
{
    /**
     * Проверяет, что GenericEntityRepository умеет exists/find/all для сущности с int PK.
     */
    #[Test]
    public function existsFindAllWorkForIntPk(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $driver = new SqliteDriver();

        $conn = new Connection($pdo, $driver);

        $conn->execute(
            '
                CREATE TABLE test_entities (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO test_entities (id, name)
                VALUES (1, 'John'), (2, 'Kate')
            ",
        );

        $repo = new GenericEntityRepository($conn, TestEntity::class);

        self::assertTrue($repo->exists(1));
        self::assertFalse($repo->exists(999));

        $e = $repo->find(2);
        self::assertNotNull($e);
        self::assertSame(2, $e->id);
        self::assertSame('Kate', $e->name);

        $all = $repo->all();
        self::assertCount(2, $all->all());
    }

    /**
     * Проверяет, что GenericEntityRepository умеет загружать сущности по общей LookupSpec.
     */
    #[Test]
    public function findManyByLookupLoadsEntities(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE test_entities (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO test_entities (id, name)
                VALUES (1, 'John'), (2, 'Kate'), (3, 'Sam')
            ",
        );

        $repo = new GenericEntityRepository($conn, TestEntity::class);

        $entities = $repo->findManyByLookup(
            LookupSpec::forTable('test_entities')
                ->lookupColumn('name')
                ->values(['Kate', 'Sam']),
        );

        self::assertSame(['Kate', 'Sam'], array_map(
            static fn (TestEntity $entity): string => $entity->name,
            $entities->all(),
        ));
    }

    /**
     * Проверяет, что GenericEntityRepository не теряет строки при warmup lookup по неуникальной колонке.
     */
    #[Test]
    public function findManyByLookupKeepsRowsForNonUniqueColumn(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $conn = new Connection($pdo, new SqliteDriver());

        $conn->execute(
            '
                CREATE TABLE test_entities (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(255) NOT NULL
                )
            ',
        );

        $conn->execute(
            "
                INSERT INTO test_entities (id, name)
                VALUES (1, 'John'), (2, 'Kate'), (3, 'Sam'), (4, 'Kate')
            ",
        );

        $repo = new GenericEntityRepository($conn, TestEntity::class);

        $entities = $repo->findManyByLookup(
            LookupSpec::forTable('test_entities')
                ->lookupColumn('name')
                ->values(['Kate']),
        );

        self::assertSame([2, 4], array_map(
            static fn (TestEntity $entity): int => $entity->id,
            $entities->all(),
        ));
    }
}
